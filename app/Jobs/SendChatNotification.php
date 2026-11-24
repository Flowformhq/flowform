<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationChannel;
use App\Models\Submission;
use App\Services\ChatNotifications\ChatNotificationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChatNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public NotificationChannel $channel,
        public Submission $submission
    ) {}

    public function handle(): void
    {
        if (! $this->channel->is_active) {
            return;
        }

        $formatter = ChatNotificationManager::formatterFor($this->channel->type);
        $formatter->send($this->channel, $this->submission);
    }
}
