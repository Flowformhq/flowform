<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TokenAbility;
use Carbon\CarbonImmutable;

class ApiTokenService
{
    public const DEFAULT_UI_EXPIRY_PRESET = '365';

    /**
     * @return list<string>
     */
    public function allowedAbilityValues(): array
    {
        return TokenAbility::values();
    }

    /**
     * @return array<int|string, string>
     */
    public function expiryOptions(): array
    {
        return [
            '30' => '30 days',
            '90' => '90 days',
            '365' => '1 year',
            'never' => 'No expiry',
        ];
    }

    public function resolveExpiresAtFromPreset(mixed $preset): ?CarbonImmutable
    {
        return match ((string) ($preset ?? self::DEFAULT_UI_EXPIRY_PRESET)) {
            '30' => now()->toImmutable()->addDays(30),
            '90' => now()->toImmutable()->addDays(90),
            'never' => null,
            default => now()->toImmutable()->addDays(365),
        };
    }

    /**
     * Parse CLI `--expires=<days>` option into a Carbon expiration timestamp.
     *
     * Returns null when omitted, or false when the provided value is invalid or non-positive.
     */
    public function parseExpiresDaysOption(mixed $option): CarbonImmutable|false|null
    {
        if ($option === null) {
            return null;
        }

        if (! is_string($option) && ! is_int($option)) {
            return false;
        }

        $trimmed = trim((string) $option);
        if ($trimmed === '' || preg_match('/^\d+$/', $trimmed) !== 1) {
            return false;
        }

        $days = (int) $trimmed;
        if ($days <= 0) {
            return false;
        }

        return now()->toImmutable()->addDays($days);
    }

    /**
     * @return list<string>|null
     */
    public function parseAbilitiesOption(mixed $option): ?array
    {
        if (! is_string($option) || trim($option) === '') {
            return ['*'];
        }

        $requested = array_values(array_unique(array_filter(array_map('trim', explode(',', $option)))));
        if ($requested === ['*']) {
            return ['*'];
        }

        if ($requested === [] || array_diff($requested, $this->allowedAbilityValues()) !== []) {
            return null;
        }

        return $requested;
    }
}
