<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FieldStateResource;
use App\Http\Resources\Api\SubmissionDetailResource;
use App\Http\Resources\Api\SubmissionResource;
use App\Models\AuditLog;
use App\Models\Field;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Services\WebhookDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * @group Submissions
 *
 * Endpoints for creating and managing form submissions.
 * All endpoints require authentication via Bearer token.
 */
class SubmissionController extends Controller
{
    /**
     * List submissions for a form
     *
     * Returns cursor-paginated submissions for a form owned by the authenticated token user, ordered newest first (`id desc`). Requires the `submissions:read` ability.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the form. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @queryParam since string ISO-8601 timestamp; returns submissions created strictly after this timestamp. Example: 2026-11-26T00:00:00Z
     * @queryParam status string Filter submissions by status (`completed`, `draft`, or `abandoned`). Example: completed
     * @queryParam per_page integer Results per page (default 25, max 100). Example: 25
     * @queryParam cursor string Opaque pagination cursor from `meta.next_cursor`.
     * @queryParam include string Set to `meta` to include sensitive metadata such as `ip`. Example: meta
     *
     * @response 200 scenario="success" {"data":[{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","status":"completed","current_step":1,"progress_percentage":0,"data":{"email":"alice@example.com"},"meta":{"source":"astro"},"created_at":"2026-11-26T00:00:00.000000Z"}],"links":{"first":null,"last":null,"prev":null,"next":null},"meta":{"path":"http://localhost/api/v1/forms/9e1a2b3c/submissions","per_page":25,"next_cursor":null,"prev_cursor":null}}
     * @response 403 scenario="missing submissions:read ability" {"message":"Invalid ability provided."}
     * @response 404 scenario="form not found or not owned by user" {"message":"Not Found"}
     * @response 422 scenario="invalid query parameter" {"message":"The since field must be a valid date."}
     */
    public function forForm(string $uuid, Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['completed', 'draft', 'abandoned'])],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $form = Form::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $perPage = min((int) ($validated['per_page'] ?? 25), 100);

        $query = $form->submissions()->with('form.steps');

        if (! empty($validated['since'])) {
            $since = Carbon::parse((string) $validated['since'])
                ->timezone((string) config('app.timezone'));
            $query->where('created_at', '>', $since);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $submissions = $query->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();

        foreach ($submissions->items() as $submission) {
            if ($submission->is_encrypted) {
                AuditLog::record(
                    action: 'submission.decrypted',
                    auditable: $submission,
                    details: ['form_uuid' => $form->uuid, 'status' => $submission->status, 'via' => 'list'],
                    user: $request->user(),
                    request: $request
                );
            }
        }

        return SubmissionResource::collection($submissions);
    }

    /**
     * Create a submission
     *
     * Creates a new draft submission for the given form. The submission starts at step 1.
     *
     * @authenticated
     *
     * @bodyParam form_uuid string required The UUID of the form to submit against. Example: 9e1a2b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c
     *
     * @response 201 scenario="created" {"data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","status":"draft","current_step":1,"progress_percentage":0,"meta":null,"created_at":"2026-04-08T00:00:00.000000Z"}}
     * @response 422 scenario="invalid form" {"message":"The selected form uuid is invalid.","errors":{"form_uuid":["The selected form uuid is invalid."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_uuid' => [
                'required',
                'string',
                Rule::exists('forms', 'uuid')->where('is_active', true),
            ],
        ]);

        $form = Form::where('uuid', $data['form_uuid'])->firstOrFail();

        $submission = Submission::create([
            'form_id' => $form->id,
            'user_id' => $this->isGuestRequest($request) ? null : $request->user()?->id,
            'status' => 'draft',
            'current_step' => 1,
        ]);

        $submission->load('form');

        return response()->json([
            'data' => (new SubmissionResource($submission))->resolve($request),
        ], 201);
    }

    /**
     * Get submission details
     *
     * Returns a submission with its current field values as a key-value map (field_code → value).
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 scenario="success" {"data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","status":"draft","current_step":1,"progress_percentage":0,"meta":null,"created_at":"2026-04-08T00:00:00.000000Z","values":{"email":"user@example.com","name":"Alice"}}}
     * @response 404 scenario="not found" {"message":"Not Found"}
     */
    public function show(string $uuid, Request $request): SubmissionDetailResource
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $submission->load(['form', 'values.field']);

        if ($submission->is_encrypted) {
            AuditLog::record(
                action: 'submission.decrypted',
                auditable: $submission,
                details: ['form_uuid' => $submission->form?->uuid, 'status' => $submission->status],
                user: $request->user(),
                request: $request
            );
        }

        return new SubmissionDetailResource($submission);
    }

    /**
     * Update submission
     *
     * Updates the submission status and/or meta data.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @bodyParam status string The submission status. Must be one of: draft, completed, abandoned. Example: completed
     * @bodyParam meta object Optional metadata to attach to the submission.
     *
     * @response 200 scenario="success" {"data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","status":"completed","current_step":1,"progress_percentage":100,"meta":null,"created_at":"2026-04-08T00:00:00.000000Z"}}
     */
    public function update(string $uuid, Request $request): JsonResponse
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $this->ensureActiveFormForPublic($submission, $request);

        $isGuest = $this->isGuestRequest($request);
        $wasCompleted = $submission->status === 'completed';

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in($isGuest ? ['completed', 'abandoned'] : ['draft', 'completed', 'abandoned'])],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        if ($isGuest && array_key_exists('meta', $data)) {
            $data['meta'] = array_merge($submission->meta ?? [], $data['meta'] ?? []);
        }

        $submission->update($data);
        $submission->load('form');

        if (! $wasCompleted && $submission->status === 'completed' && $submission->form !== null) {
            try {
                app(WebhookDispatcher::class)->dispatchForSubmission($submission->form, $submission);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'data' => (new SubmissionResource($submission))->resolve($request),
        ]);
    }

    /**
     * Store field values
     *
     * Upserts field values for a submission. If a value for a field already exists, it is updated.
     * Fields are identified by their `code` (not ID).
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @bodyParam values array required Array of field values to store.
     * @bodyParam values[].field_code string required The field code. Example: email
     * @bodyParam values[].value string The field value. Example: user@example.com
     *
     * @response 200 scenario="success" {"data":{"uuid":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","status":"draft","current_step":1,"progress_percentage":0,"meta":null,"created_at":"2026-04-08T00:00:00.000000Z","values":{"email":"user@example.com"}}}
     */
    public function storeValues(string $uuid, Request $request): SubmissionDetailResource
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $this->ensureActiveFormForPublic($submission, $request);

        $data = $request->validate([
            'values' => ['required', 'array'],
            'values.*.field_code' => ['required', 'string'],
            'values.*.value' => ['nullable', 'string'],
        ]);

        // Build field code → id map for this form
        $fieldMap = Field::where('form_id', $submission->form_id)
            ->pluck('id', 'code');

        foreach ($data['values'] as $entry) {
            $fieldId = $fieldMap[$entry['field_code']] ?? null;
            if ($fieldId === null) {
                continue;
            }

            SubmissionValue::updateOrCreate(
                ['submission_id' => $submission->id, 'field_id' => $fieldId],
                ['value' => $entry['value']],
            );
        }

        $submission->load(['form', 'values.field']);

        return new SubmissionDetailResource($submission);
    }

    /**
     * Advance step
     *
     * Moves the submission to the next step. Returns 422 if already on the last step.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 scenario="advanced" {"current_step":2}
     * @response 422 scenario="last step" {"message":"Already on the last step."}
     */
    public function advance(string $uuid, Request $request): JsonResponse
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $this->ensureActiveFormForPublic($submission, $request);

        if (! $submission->advanceStep()) {
            return response()->json(['message' => 'Already on the last step.'], 422);
        }

        return response()->json(['current_step' => $submission->fresh()->current_step]);
    }

    /**
     * Retreat step
     *
     * Moves the submission back to the previous step. Returns 422 if already on the first step.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 scenario="retreated" {"current_step":1}
     * @response 422 scenario="first step" {"message":"Already on the first step."}
     */
    public function retreat(string $uuid, Request $request): JsonResponse
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $this->ensureActiveFormForPublic($submission, $request);

        if (! $submission->retreatStep()) {
            return response()->json(['message' => 'Already on the first step.'], 422);
        }

        return response()->json(['current_step' => $submission->fresh()->current_step]);
    }

    /**
     * Evaluate conditions
     *
     * Evaluates all field conditions for a submission based on its current values.
     * Returns the visibility and required state of each field.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the submission. Example: a1b2c3d4-e5f6-7890-abcd-ef1234567890
     *
     * @response 200 scenario="success" {"data":[{"field_id":1,"field_code":"email","is_visible":true,"is_required":true},{"field_id":2,"field_code":"company","is_visible":false,"is_required":false}]}
     */
    public function conditions(string $uuid, Request $request): JsonResponse
    {
        $submission = $this->resolveSubmission($uuid, $request);
        $submission->load(['form.fields.conditions', 'values']);

        $states = $submission->evaluateConditions();

        // Build field code lookup
        $fields = $submission->form->fields->keyBy('id');

        $resources = collect($states)->map(function ($state, $fieldId) use ($fields) {
            $field = $fields[$fieldId];

            return new FieldStateResource($fieldId, $field->code, $state);
        })->values();

        return response()->json(['data' => $resources]);
    }

    /**
     * Resolve submission scoped by tenant / object-level tenancy authorization.
     */
    protected function resolveSubmission(string $uuid, Request $request): Submission
    {
        $query = Submission::where('uuid', $uuid);

        $user = $this->isGuestRequest($request) ? null : $request->user();

        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('form', function ($formQuery) use ($user) {
                        $formQuery->where('user_id', $user->id);
                    })
                    ->when($user->is_admin, fn ($adminQuery) => $adminQuery->orWhere(function ($legacy) {
                        $legacy->whereNull('user_id')
                            ->whereHas('form', fn ($fq) => $fq->whereNull('user_id'));
                    }));
            });
        } else {
            $query->where('status', 'draft')->whereNull('user_id');
        }

        return $query->firstOrFail();
    }

    // Keyed on the route, not on $request->user(), so a session or token user reaching a public route still gets guest scoping.
    protected function isGuestRequest(Request $request): bool
    {
        return $request->is('api/v1/public/*');
    }

    protected function ensureActiveFormForPublic(Submission $submission, Request $request): void
    {
        if ($this->isGuestRequest($request) && ! $submission->form->is_active) {
            abort(422, 'This form is currently inactive.');
        }
    }
}
