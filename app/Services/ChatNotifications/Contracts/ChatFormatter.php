<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications\Contracts;

use App\Models\NotificationChannel;
use App\Models\Submission;

interface ChatFormatter
{
    /**
     * Format the channel submission into a target URL and request payload.
     *
     * @return array{url: string, payload: array<string, mixed>}
     */
    public function format(NotificationChannel $channel, Submission $submission): array;

    /**
     * Send the notification to the chat service.
     */
    public function send(NotificationChannel $channel, Submission $submission): bool;
}
