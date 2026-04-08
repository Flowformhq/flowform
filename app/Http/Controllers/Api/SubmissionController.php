<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FieldStateResource;
use App\Http\Resources\Api\SubmissionDetailResource;
use App\Http\Resources\Api\SubmissionResource;
use App\Models\Field;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValue;
use Illuminate\Http\Request;
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
    public function store(Request $request)
    {
        $data = $request->validate([
            'form_uuid' => ['required', 'string', Rule::exists('forms', 'uuid')],
        ]);

        $form = Form::where('uuid', $data['form_uuid'])->firstOrFail();

        $submission = Submission::create([
            'form_id' => $form->id,
            'status' => 'draft',
            'current_step' => 1,
        ]);

        $submission->load('form');

        return (new SubmissionResource($submission))->response()->setStatusCode(201);
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
    public function show(string $uuid)
    {
        $submission = Submission::where('uuid', $uuid)
            ->with(['form', 'values.field'])
            ->firstOrFail();

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
    public function update(string $uuid, Request $request)
    {
        $submission = Submission::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['draft', 'completed', 'abandoned'])],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $submission->update($data);
        $submission->load('form');

        return new SubmissionResource($submission);
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
    public function storeValues(string $uuid, Request $request)
    {
        $submission = Submission::where('uuid', $uuid)->firstOrFail();

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
    public function advance(string $uuid)
    {
        $submission = Submission::where('uuid', $uuid)->firstOrFail();

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
    public function retreat(string $uuid)
    {
        $submission = Submission::where('uuid', $uuid)->firstOrFail();

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
    public function conditions(string $uuid)
    {
        $submission = Submission::where('uuid', $uuid)
            ->with(['form.fields.conditions', 'values'])
            ->firstOrFail();

        $states = $submission->evaluateConditions();

        // Build field code lookup
        $fields = $submission->form->fields->keyBy('id');

        $resources = collect($states)->map(function ($state, $fieldId) use ($fields) {
            $field = $fields[$fieldId];

            return new FieldStateResource($fieldId, $field->code, $state);
        })->values();

        return response()->json(['data' => $resources]);
    }
}
