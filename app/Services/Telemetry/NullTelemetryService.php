<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

class NullTelemetryService implements TelemetryContract
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function isOptedIn(): bool
    {
        return false;
    }

    public function optIn(): void {}

    public function optOut(): void {}

    public function ping(): TelemetryResult
    {
        return TelemetryResult::skipped();
    }
}
