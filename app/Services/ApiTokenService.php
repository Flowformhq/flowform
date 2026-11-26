<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TokenAbility;

class ApiTokenService
{
    /**
     * @return list<string>
     */
    public function allowedAbilityValues(): array
    {
        return TokenAbility::values();
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
