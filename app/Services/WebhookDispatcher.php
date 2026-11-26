<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Jobs\DispatchFormWebhook;
use App\Models\AuditLog;
use App\Models\Field;
use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Support\Collection;

class WebhookDispatcher
{
    /**
     * Dispatch webhook jobs for all active matching subscriptions and the legacy per-form webhook URL.
     *
     * @param  array<array-key, mixed>|null  $preloadedPayload
     */
    public function dispatchForSubmission(
        Form $form,
        Submission $submission,
        ?array $preloadedPayload = null,
        string $event = 'submission.created',
    ): void {
        $hasLegacy = ! empty($form->webhook_url);
        $subscriptions = $this->matchingSubscriptions($form, $event);

        if (! $hasLegacy && $subscriptions->isEmpty()) {
            return;
        }

        $payload = $preloadedPayload ?? $this->resolvePayload($submission);

        if ($submission->is_encrypted) {
            AuditLog::record(
                action: 'submission.decrypted',
                auditable: $submission,
                details: ['form_uuid' => $form->uuid, 'status' => $submission->status, 'via' => 'webhook'],
                user: $form->user,
                request: request()
            );
        }

        if ($hasLegacy) {
            DispatchFormWebhook::dispatch($form, $submission, $payload);
        }

        foreach ($subscriptions as $subscription) {
            DeliverWebhook::dispatch($form, $submission, $payload, $subscription, $event);
        }
    }

    /**
     * @return Collection<int, WebhookSubscription>
     */
    protected function matchingSubscriptions(Form $form, string $event): Collection
    {
        if ($form->user_id === null) {
            return collect();
        }

        return WebhookSubscription::query()
            ->active()
            ->where('user_id', $form->user_id)
            ->where(function ($query) use ($form): void {
                $query->where('form_id', $form->id)->orWhereNull('form_id');
            })
            ->get()
            ->filter(fn (WebhookSubscription $subscription): bool => $subscription->supportsEvent($event))
            ->values();
    }

    /**
     * Re-enqueue a failed webhook delivery using DeliverWebhook and the original delivery key.
     */
    public function retryDelivery(WebhookDelivery $delivery, ?User $actor = null): bool
    {
        if ($delivery->status !== WebhookDelivery::STATUS_FAILED) {
            return false;
        }

        $alreadySucceeded = WebhookDelivery::where('delivery_key', $delivery->delivery_key)
            ->where('status', WebhookDelivery::STATUS_SUCCEEDED)
            ->exists();

        if ($alreadySucceeded) {
            return false;
        }

        $form = $delivery->form;
        $submission = $delivery->submission;
        if (! $form || ! $submission) {
            return false;
        }

        $subscription = $delivery->subscription;
        if ($delivery->webhook_subscription_id !== null) {
            if (! $subscription || ! $subscription->is_active || $subscription->disabled_at !== null) {
                return false;
            }
        } elseif (empty($form->webhook_url)) {
            return false;
        }

        $payload = $this->resolvePayload($submission);

        if ($submission->is_encrypted) {
            AuditLog::record(
                action: 'submission.decrypted',
                auditable: $submission,
                details: ['form_uuid' => $form->uuid, 'status' => $submission->status, 'via' => 'webhook_retry'],
                user: $actor ?? $form->user,
                request: request()
            );
        }

        DeliverWebhook::dispatch($form, $submission, $payload, $subscription, $delivery->event);

        return true;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function resolvePayload(Submission $submission): array
    {
        $data = $submission->getDataWithSignedUrls();
        $submission->loadMissing('values.field');

        $wizardValues = [];
        foreach ($submission->values as $submissionValue) {
            /** @var SubmissionValue $submissionValue */
            /** @var Field|null $field */
            $field = $submissionValue->field;
            if ($field !== null) {
                $wizardValues[$field->code] = $submissionValue->value;
            }
        }

        return array_merge($data, $wizardValues);
    }
}
