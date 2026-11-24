<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Formatters;

use App\Models\NotificationChannel;
use App\Models\Submission;

class TelegramFormatter extends BaseChatFormatter
{
    private const MESSAGE_CHAR_LIMIT = 4000;

    public function format(NotificationChannel $channel, Submission $submission): array
    {
        $form = $submission->relationLoaded('form') ? $submission->form : ($channel->form ?? $submission->form);
        $formName = $form ? $form->name : 'Form';
        $fields = $this->extractFields($channel, $submission, maxFields: 25, maxChars: 500);

        $lines = [];
        $lines[] = '<b>New Submission: '.htmlspecialchars($formName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</b>';
        $lines[] = '';
        $footer = "\n<i>Submission: ".htmlspecialchars($submission->uuid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</i>';

        if (empty($fields)) {
            $lines[] = '<i>No form fields submitted.</i>';
        }

        foreach ($fields as $key => $val) {
            $safeKey = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeVal = htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $line = "<b>{$safeKey}:</b> {$safeVal}";

            if (mb_strlen(implode("\n", [...$lines, $line]).$footer) > self::MESSAGE_CHAR_LIMIT) {
                $lines[] = '<i>(more fields omitted)</i>';
                break;
            }

            $lines[] = $line;
        }

        $lines[] = $footer;

        $token = (string) ($channel->config['bot_token'] ?? '');
        $chatId = $channel->config['chat_id'] ?? '';

        return [
            'url' => "https://api.telegram.org/bot{$token}/sendMessage",
            'payload' => [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'text' => implode("\n", $lines),
            ],
        ];
    }
}
