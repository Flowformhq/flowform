<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;

class DiscordFormatter extends BaseChatFormatter
{
    public function format(NotificationChannel $channel, Submission $submission): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $formName = $form ? $form->name : 'Form';
        $fields = $this->extractFields($channel, $submission, maxFields: 25, maxChars: 1024);

        $embedFields = [];
        foreach ($fields as $key => $val) {
            $embedFields[] = [
                'name' => mb_substr((string) $key, 0, 256),
                'value' => $val !== '' ? $val : '-',
                'inline' => true,
            ];
        }

        $time = $submission->created_at?->toIso8601String() ?? now()->toIso8601String();

        return [
            'url' => (string) ($channel->config['webhook_url'] ?? ''),
            'payload' => [
                'embeds' => [
                    [
                        'title' => 'New Submission: '.$formName,
                        'fields' => $embedFields,
                        'timestamp' => $time,
                    ],
                ],
            ],
        ];
    }
}
