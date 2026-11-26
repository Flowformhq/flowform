<?php

declare(strict_types=1);

namespace App\Support;

enum TokenAbility: string
{
    case FormsRead = 'forms:read';
    case FormsWrite = 'forms:write';
    case SubmissionsRead = 'submissions:read';
    case SubmissionsWrite = 'submissions:write';
    case WebhooksManage = 'webhooks:manage';

    public function label(): string
    {
        return match ($this) {
            self::FormsRead => 'Read forms and schemas',
            self::FormsWrite => 'Create and modify forms',
            self::SubmissionsRead => 'Read submissions',
            self::SubmissionsWrite => 'Create and modify submissions',
            self::WebhooksManage => 'Manage webhook subscriptions',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $ability): string => $ability->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = "{$case->value} ({$case->label()})";
        }

        return $options;
    }
}
