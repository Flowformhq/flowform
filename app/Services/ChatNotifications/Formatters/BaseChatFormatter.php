<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;
use App\Services\ChatNotifications\ChatNotificationManager;
use App\Services\ChatNotifications\Contracts\ChatFormatter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseChatFormatter implements ChatFormatter
{
    /**
     * @return array<string, string>
     */
    protected function extractFields(NotificationChannel $channel, Submission $submission, int $maxFields, int $maxChars): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $isFormEncrypted = (bool) ($form?->is_encrypted || $submission->is_encrypted);
        $encryptedFields = is_array($form?->encrypted_fields) ? $form->encrypted_fields : [];

        $data = $submission->data ?? [];

        $result = [];
        $count = 0;

        foreach ($data as $key => $value) {
            if ($count >= $maxFields) {
                break;
            }

            $keyStr = (string) $key;
            if ($isFormEncrypted || in_array($keyStr, $encryptedFields, true)) {
                $formattedValue = '[encrypted]';
            } else {
                if (is_array($value)) {
                    if (isset($value['name']) && is_string($value['name'])) {
                        $formattedValue = $value['name'];
                    } else {
                        $formattedValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
                    }
                } elseif ($value === null) {
                    $formattedValue = '-';
                } elseif (is_bool($value)) {
                    $formattedValue = $value ? 'true' : 'false';
                } else {
                    $formattedValue = (string) $value;
                }

                if (mb_strlen($formattedValue) > $maxChars) {
                    $formattedValue = mb_substr($formattedValue, 0, $maxChars);
                }
            }

            $result[$keyStr] = $formattedValue;
            $count++;
        }

        return $result;
    }

    public function send(NotificationChannel $channel, Submission $submission): bool
    {
        $formatted = $this->format($channel, $submission);
        $url = $formatted['url'];
        $payload = $formatted['payload'];

        if (! ChatNotificationManager::isAllowedChatHost($channel->type, $url)) {
            Log::warning('FlowForm Chat Notification rejected for host', [
                'channel_id' => $channel->id,
                'type' => $channel->type,
            ]);

            return false;
        }

        $response = Http::timeout(10)->withoutRedirecting()->post($url, $payload);

        if (! $response->successful()) {
            Log::error('FlowForm Chat Notification HTTP request failed', [
                'channel_id' => $channel->id,
                'type' => $channel->type,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        return true;
    }
}
