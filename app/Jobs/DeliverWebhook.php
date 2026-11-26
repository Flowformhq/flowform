<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Form;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Rules\ValidWebhookUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_FAILURES_BEFORE_DISABLE = 50;

    public const MAX_RESPONSE_BODY_BYTES = 2048;

    public int $tries = 6;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 1800, 7200];

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        public Form $form,
        public Submission $submission,
        public array $payload = [],
        public ?WebhookSubscription $subscription = null,
        public string $event = 'submission.created',
    ) {}

    public function handle(): void
    {
        $subscription = $this->resolveActiveSubscription();
        if ($this->subscription !== null && $subscription === null) {
            return;
        }

        $targetUrl = $subscription ? $subscription->target_url : $this->form->webhook_url;
        if (empty($targetUrl)) {
            return;
        }

        $deliveryKey = $this->deliveryKey($subscription);
        if ($this->alreadySucceeded($deliveryKey)) {
            return;
        }

        $pinned = null;
        if (! config('flowform.webhooks.allow_private_targets', false)) {
            $pinned = ValidWebhookUrl::resolveAllowedTarget($targetUrl);
            if ($pinned === null) {
                Log::warning('FlowForm Webhook target rejected (private or unresolvable host)', [
                    'form_id' => $this->form->id,
                    'subscription_id' => $subscription?->id,
                    'host' => parse_url($targetUrl, PHP_URL_HOST),
                ]);

                return;
            }
        }

        $encodedBody = $this->encodePayload($subscription);
        $headers = $this->buildHeaders($encodedBody, $subscription);
        $delivery = $this->createPendingDelivery($subscription, $deliveryKey);
        $startedAt = hrtime(true);

        try {
            $request = Http::withoutRedirecting()
                ->withHeaders($headers)
                ->timeout(15);

            if ($pinned !== null) {
                $address = str_contains($pinned['ip'], ':') ? "[{$pinned['ip']}]" : $pinned['ip'];
                $request = $request->withOptions([
                    'curl' => [CURLOPT_RESOLVE => ["{$pinned['host']}:{$pinned['port']}:{$address}"]],
                ]);
            }

            $response = $request->withBody($encodedBody, 'application/json')->post($targetUrl);
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $truncatedBody = mb_strcut($response->body(), 0, self::MAX_RESPONSE_BODY_BYTES, 'UTF-8');

            if ($response->successful()) {
                $delivery?->update([
                    'status' => WebhookDelivery::STATUS_SUCCEEDED,
                    'response_code' => $response->status(),
                    'response_body' => $truncatedBody,
                    'duration_ms' => $durationMs,
                ]);

                if ($subscription !== null && $subscription->failure_count > 0) {
                    $subscription->forceFill(['failure_count' => 0])->save();
                }

                Log::info("FlowForm Webhook delivered successfully for Form #{$this->form->id} ({$this->submission->uuid})");

                return;
            }

            $delivery?->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'response_code' => $response->status(),
                'response_body' => $truncatedBody,
                'duration_ms' => $durationMs,
            ]);

            if ($response->status() === 410 && $subscription !== null) {
                $subscription->delete();

                return;
            }

            Log::warning("FlowForm Webhook delivery failed for Form #{$this->form->id}", [
                'url' => $targetUrl,
                'subscription_id' => $subscription?->id,
                'status' => $response->status(),
            ]);

            $this->recordFailureAndScheduleRetry($subscription, $delivery !== null ? $delivery->attempt : $this->attempts());
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $delivery?->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'response_body' => mb_strcut($e->getMessage(), 0, self::MAX_RESPONSE_BODY_BYTES, 'UTF-8'),
                'duration_ms' => $durationMs,
            ]);

            Log::error("FlowForm Webhook connection exception: {$e->getMessage()}", [
                'url' => $targetUrl,
                'subscription_id' => $subscription?->id,
                'attempt' => $this->attempts(),
            ]);

            $this->recordFailureAndScheduleRetry($subscription, $delivery !== null ? $delivery->attempt : $this->attempts());
        }
    }

    protected function resolveActiveSubscription(): ?WebhookSubscription
    {
        if ($this->subscription === null) {
            return null;
        }

        $fresh = $this->subscription->fresh();
        if (! $fresh || ! $fresh->is_active || $fresh->disabled_at !== null) {
            return null;
        }

        return $fresh;
    }

    protected function deliveryKey(?WebhookSubscription $subscription): string
    {
        $submissionKey = $this->submission->exists
            ? (string) $this->submission->getKey()
            : (string) $this->submission->uuid;
        $subscriptionKey = $subscription !== null ? (string) $subscription->id : 'legacy';

        return "{$submissionKey}:{$subscriptionKey}";
    }

    protected function deliveryId(?WebhookSubscription $subscription): string
    {
        $hash = hash('sha256', "{$this->submission->uuid}:".($subscription !== null ? (string) $subscription->uuid : 'legacy'));

        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec(substr($hash, 16, 2)) & 0x3F) | 0x80),
            substr($hash, 18, 2),
            substr($hash, 20, 12)
        );
    }

    protected function alreadySucceeded(string $deliveryKey): bool
    {
        if (! $this->submission->exists) {
            return false;
        }

        return WebhookDelivery::where('delivery_key', $deliveryKey)
            ->where('status', WebhookDelivery::STATUS_SUCCEEDED)
            ->exists();
    }

    protected function encodePayload(?WebhookSubscription $subscription): string
    {
        $body = [
            'event' => $this->event,
            'subscription_id' => $subscription?->uuid,
            'form' => [
                'id' => $this->form->uuid,
                'name' => $this->form->name,
                'slug' => $this->form->slug,
            ],
            'submission' => [
                'id' => $this->submission->uuid,
                'status' => $this->submission->status,
                'submitted_at' => $this->submission->created_at?->toISOString() ?? now()->toISOString(),
                'data' => $this->payload,
                'meta' => $this->submission->meta ?? [],
            ],
        ];

        return (string) json_encode($body);
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(string $encodedBody, ?WebhookSubscription $subscription): array
    {
        $timestamp = (string) time();
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'FlowForm-Webhook/1.0',
            'X-FlowForm-Event' => $this->event,
            'X-FlowForm-Submission-Id' => (string) $this->submission->uuid,
            'X-FlowForm-Delivery-Id' => $this->deliveryId($subscription),
            'X-FlowForm-Timestamp' => $timestamp,
        ];

        $secret = $subscription ? $subscription->secret : $this->form->webhook_secret;
        if (! empty($secret)) {
            $headers['X-FlowForm-Signature'] = hash_hmac('sha256', $encodedBody, $secret);
            $signatureV2 = hash_hmac('sha256', "{$timestamp}.{$encodedBody}", $secret);
            $headers['X-FlowForm-Signature-256'] = "t={$timestamp},v1={$signatureV2}";
        }

        return $headers;
    }

    protected function createPendingDelivery(?WebhookSubscription $subscription, string $deliveryKey): ?WebhookDelivery
    {
        if (! $this->submission->exists) {
            return null;
        }

        $priorCount = WebhookDelivery::where('delivery_key', $deliveryKey)->count();
        $attempt = max($this->attempts(), $priorCount + 1);

        return WebhookDelivery::create([
            'webhook_subscription_id' => $subscription?->id,
            'form_id' => $this->form->id,
            'submission_id' => $this->submission->id,
            'delivery_key' => $deliveryKey,
            'event' => $this->event,
            'attempt' => $attempt,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempted_at' => now(),
        ]);
    }

    protected function recordFailureAndScheduleRetry(?WebhookSubscription $subscription, int $attempt): void
    {
        if ($subscription !== null) {
            $failures = $subscription->failure_count + 1;
            $updates = ['failure_count' => $failures];

            if ($failures >= self::MAX_FAILURES_BEFORE_DISABLE) {
                $updates['disabled_at'] = now();
                $updates['is_active'] = false;
            }

            $subscription->forceFill($updates)->save();

            if ($failures >= self::MAX_FAILURES_BEFORE_DISABLE) {
                return;
            }
        }

        if ($attempt < $this->tries) {
            $this->release($this->backoff[$attempt - 1] ?? 7200);
        }
    }
}
