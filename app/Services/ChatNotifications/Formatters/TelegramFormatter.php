<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;

class TelegramFormatter extends BaseChatFormatter
{
    public function format(NotificationChannel $channel, Submission $submission): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $formName = $form ? $form->name : 'Form';
        $fields = $this->extractFields($channel, $submission, maxFields: 25, maxChars: 500);

        $lines = [];
        $lines[] = '<b>New Submission: '.htmlspecialchars($formName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</b>';
        $lines[] = '';

        if (empty($fields)) {
            $lines[] = '<i>No form fields submitted.</i>';
        } else {
            foreach ($fields as $key => $val) {
                $safeKey = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeVal = htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines[] = "<b>{$safeKey}:</b> {$safeVal}";
            }
        }

        $lines[] = '';
        $lines[] = '<i>Submission: '.htmlspecialchars($submission->uuid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</i>';

        $token = (string) ($channel->config['bot_token'] ?? '');
        $chatId = $channel->config['chat_id'] ?? '';

        return [
            'url' => "https://api.telegram.org/bot{$token}/sendMessage",
            'payload' => [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => mb_substr(implode("\n", $lines), 0, 4096),
            ],
        ];
    }
}
