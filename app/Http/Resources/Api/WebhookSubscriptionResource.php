<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WebhookSubscription */
class WebhookSubscriptionResource extends JsonResource
{
    protected bool $exposeSecret = false;

    public function withSecret(bool $expose = true): self
    {
        $this->exposeSecret = $expose;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'target_url' => $this->target_url,
            'form_uuid' => $this->form?->uuid,
            'events' => $this->events,
            'source' => $this->source,
            'is_active' => (bool) $this->is_active,
            'failure_count' => (int) $this->failure_count,
            'disabled_at' => $this->disabled_at,
            'created_at' => $this->created_at,
        ];

        if ($this->exposeSecret) {
            $payload['secret'] = $this->secret;
        }

        return $payload;
    }
}
