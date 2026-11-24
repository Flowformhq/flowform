<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;

class SlackFormatter extends BaseChatFormatter
{
    public function format(NotificationChannel $channel, Submission $submission): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $formName = $form ? $form->name : 'Form';
        $fields = $this->extractFields($channel, $submission, maxFields: 20, maxChars: 500);

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => mb_substr('New Submission: '.$formName, 0, 150),
                    'emoji' => true,
                ],
            ],
        ];

        if (empty($fields)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '_No form fields submitted._',
                ],
            ];
        } else {
            $fieldElements = [];
            foreach ($fields as $key => $val) {
                $fieldElements[] = [
                    'type' => 'mrkdwn',
                    'text' => "*{$key}:*\n{$val}",
                ];
            }

            foreach (array_chunk($fieldElements, 10) as $chunk) {
                $blocks[] = [
                    'type' => 'section',
                    'fields' => $chunk,
                ];
            }
        }

        $time = $submission->created_at?->toIso8601String() ?? now()->toIso8601String();
        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "Submission: {$submission->uuid} | {$time}",
                ],
            ],
        ];

        return [
            'url' => (string) ($channel->config['webhook_url'] ?? ''),
            'payload' => [
                'blocks' => $blocks,
            ],
        ];
    }
}
