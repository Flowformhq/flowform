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
     * @return array<array-key, mixed>
     */
    protected function resolvePayload(Submission $submission): array
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
