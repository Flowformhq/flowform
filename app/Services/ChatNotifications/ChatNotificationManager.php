<?php

declare(strict_types=1);

namespace App\Services\ChatNotifications;

use App\Models\NotificationChannel;
use App\Rules\ValidWebhookUrl;
use App\Services\ChatNotifications\Contracts\ChatFormatter;
use App\Services\ChatNotifications\Formatters\DiscordFormatter;
use App\Services\ChatNotifications\Formatters\SlackFormatter;
use App\Services\ChatNotifications\Formatters\TelegramFormatter;
use InvalidArgumentException;

class ChatNotificationManager
{
    public static function formatterFor(string $type): ChatFormatter
    {
        return match ($type) {
            NotificationChannel::TYPE_SLACK => new SlackFormatter,
            NotificationChannel::TYPE_DISCORD => new DiscordFormatter,
            NotificationChannel::TYPE_TELEGRAM => new TelegramFormatter,
            default => throw new InvalidArgumentException("Unsupported chat notification channel type: [{$type}]"),
        };
    }

    public static function isAllowedChatHost(string $type, string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (empty($host)) {
            return false;
        }

        $allowedHosts = match ($type) {
            NotificationChannel::TYPE_SLACK => ['hooks.slack.com'],
            NotificationChannel::TYPE_DISCORD => ['discord.com', 'discordapp.com'],
            NotificationChannel::TYPE_TELEGRAM => ['api.telegram.org'],
            default => [],
        };

        if (! in_array($host, $allowedHosts, true)) {
            return false;
        }

        return ValidWebhookUrl::isTargetAllowed($url);
    }
}
