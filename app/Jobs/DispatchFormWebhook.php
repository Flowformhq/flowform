<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchFormWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Retry backoff delays in seconds (10s, 60s, 300s).
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public Form $form,
        public Submission $submission,
        public array $payload = []
    ) {}

    public function handle(): void
    {
        $url = $this->form->webhook_url;
        if (empty($url)) {
            return;
        }

        $body = [
            'event' => 'form.submission',
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

        $encodedBody = json_encode($body);
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'FlowForm-Webhook/1.0',
            'X-FlowForm-Event' => 'form.submission',
            'X-FlowForm-Submission-Id' => $this->submission->uuid,
            'X-FlowForm-Timestamp' => (string) time(),
        ];

        if (! empty($this->form->webhook_secret)) {
            $headers['X-FlowForm-Signature'] = hash_hmac('sha256', $encodedBody, $this->form->webhook_secret);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post($url, $body);

            if (! $response->successful()) {
                Log::warning("FlowForm Webhook delivery failed for Form #{$this->form->id}", [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            } else {
                Log::info("FlowForm Webhook delivered successfully for Form #{$this->form->id} ({$this->submission->uuid})");
            }
        } catch (\Throwable $e) {
            Log::error("FlowForm Webhook connection exception: {$e->getMessage()}", [
                'url' => $url,
                'attempt' => $this->attempts(),
            ]);
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
