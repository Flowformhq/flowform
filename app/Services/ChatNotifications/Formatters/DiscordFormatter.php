<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;

class DiscordFormatter extends BaseChatFormatter
{
    private const EMBED_CHAR_LIMIT = 6000;

    public function format(NotificationChannel $channel, Submission $submission): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $formName = $form ? $form->name : 'Form';
        $fields = $this->extractFields($channel, $submission, maxFields: 25, maxChars: 1024);

        $title = mb_substr('New Submission: '.$formName, 0, 256);
        $budget = self::EMBED_CHAR_LIMIT - mb_strlen($title);
        $embedFields = [];
        foreach ($fields as $key => $val) {
            $name = mb_substr((string) $key, 0, 256);
            $room = $budget - mb_strlen($name);
            if ($room < 1) {
                break;
            }

            $value = mb_substr($val !== '' ? $val : '-', 0, $room);

            $budget -= mb_strlen($name) + mb_strlen($value);
            $embedFields[] = ['name' => $name, 'value' => $value, 'inline' => true];
        }

        $time = $submission->created_at?->toIso8601String() ?? now()->toIso8601String();

        return [
            'url' => (string) ($channel->config['webhook_url'] ?? ''),
            'payload' => [
                'allowed_mentions' => ['parse' => []],
                'embeds' => [
                    [
                        'title' => $title,
                        'fields' => $embedFields,
                        'timestamp' => $time,
                    ],
                ],
            ],
        ];
    }
}
