<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

interface TelemetryContract
{
    public function isEnabled(): bool;

    public function isOptedIn(): bool;

    public function optIn(): void;

    public function optOut(): void;

    public function ping(): TelemetryResult;
}
