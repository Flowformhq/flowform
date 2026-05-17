<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

class TelemetryResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?int $statusCode = null,
    ) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function skipped(): self
    {
        return new self(false);
    }

    public static function failed(int $statusCode): self
    {
        return new self(false, $statusCode);
    }
}
