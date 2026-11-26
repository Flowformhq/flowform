<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\WebhookSubscriptionResource;
use App\Models\Form;
use App\Models\WebhookSubscription;
use App\Rules\ValidWebhookUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * @group Webhooks
 *
 * REST hook subscription management for automation integrations and custom apps.
 * All endpoints require authentication via a bearer token carrying the `webhooks:manage` ability.
 */
class WebhookController extends Controller
{
    /**
     * List webhook subscriptions
     *
     * Returns all webhook subscriptions belonging to the authenticated token user. Signing secrets are omitted after creation.
     *
     * @authenticated
     *
     * @response 200 scenario="success" {"data":[{"id":"8b1e3f2a-4c5d-4e6f-9a0b-1c2d3e4f5a6b","uuid":"8b1e3f2a-4c5d-4e6f-9a0b-1c2d3e4f5a6b","target_url":"https://hooks.example.com/flowform","form_uuid":null,"events":["submission.created"],"source":"zapier","is_active":true,"failure_count":0,"disabled_at":null,"created_at":"2026-11-27T00:00:00.000000Z"}]}
     * @response 403 scenario="missing webhooks:manage ability" {"message":"Invalid ability provided."}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = WebhookSubscription::where('user_id', $request->user()->id)
            ->with('form:id,uuid')
            ->latest('id')
            ->paginate(25);

        return WebhookSubscriptionResource::collection($subscriptions);
    }

    /**
     * Create webhook subscription
     *
     * Registers a REST hook subscription for a specific owned form or all of the user's forms when `form_uuid` is omitted. The signing secret (`whsec_...`) is returned only in this creation response.
     *
     * @authenticated
     *
     * @bodyParam target_url string required Public https/http destination URL. Example: https://hooks.example.com/flowform
     * @bodyParam form_uuid string nullable Optional UUID of an owned form; null subscribes to all forms owned by the user.
     * @bodyParam events array Optional list of subscribed events (`submission.created`). Example: ["submission.created"]
     * @bodyParam source string nullable Optional integration identifier (e.g. `zapier`, `n8n`, `make`, `api`). Example: zapier
     *
     * @response 201 scenario="created" {"data":{"id":"8b1e3f2a-4c5d-4e6f-9a0b-1c2d3e4f5a6b","uuid":"8b1e3f2a-4c5d-4e6f-9a0b-1c2d3e4f5a6b","target_url":"https://hooks.example.com/flowform","form_uuid":null,"events":["submission.created"],"source":"zapier","is_active":true,"failure_count":0,"disabled_at":null,"created_at":"2026-11-27T00:00:00.000000Z","secret":"whsec_example"}}
     * @response 404 scenario="form not found or not owned by user" {"message":"Not Found"}
     * @response 422 scenario="invalid target url or event" {"message":"The target url points to a restricted internal or private network host."}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_url' => ['required', 'string', 'max:2048', new ValidWebhookUrl],
            'form_uuid' => ['sometimes', 'nullable', 'string'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(WebhookSubscription::SUPPORTED_EVENTS)],
            'source' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $activeCount = WebhookSubscription::active()->where('user_id', $request->user()->id)->count();
        if ($activeCount >= WebhookSubscription::MAX_ACTIVE_PER_USER) {
            return response()->json([
                'message' => 'Active webhook subscription limit reached ('.WebhookSubscription::MAX_ACTIVE_PER_USER.'). Delete unused subscriptions first.',
            ], 422);
        }

        $formId = null;
        if (! empty($validated['form_uuid'])) {
            $form = Form::where('uuid', $validated['form_uuid'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
            $formId = $form->id;
        }

        $subscription = WebhookSubscription::create([
            'user_id' => $request->user()->id,
            'form_id' => $formId,
            'target_url' => $validated['target_url'],
            'events' => $validated['events'] ?? WebhookSubscription::SUPPORTED_EVENTS,
            'source' => $validated['source'] ?? 'api',
            'is_active' => true,
        ]);

        $subscription->load('form:id,uuid');

        return (new WebhookSubscriptionResource($subscription))
            ->withSecret()
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete webhook subscription
     *
     * Unsubscribes and removes the webhook subscription belonging to the authenticated token user.
     *
     * @authenticated
     *
     * @urlParam uuid string required The UUID of the webhook subscription. Example: 8b1e3f2a-4c5d-4e6f-9a0b-1c2d3e4f5a6b
     *
     * @response 204 scenario="deleted" {}
     * @response 404 scenario="subscription not found or not owned by user" {"message":"Not Found"}
     */
    public function destroy(string $uuid, Request $request): Response
    {
        $subscription = WebhookSubscription::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $subscription->delete();

        return response()->noContent();
    }
}
