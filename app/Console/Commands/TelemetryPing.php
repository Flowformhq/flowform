<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telemetry\TelemetryService;
use Illuminate\Console\Command;

class TelemetryPing extends Command
{
    protected $signature = 'flowform:telemetry:ping';

    protected $description = 'Send an anonymous telemetry ping (called by scheduler)';

    public function handle(TelemetryService $telemetry): int
    {
        if (! $telemetry->isEnabled()) {
            $this->info('Telemetry is disabled via config.');

            return self::SUCCESS;
        }

        if (! $telemetry->isOptedIn()) {
            $this->info('Telemetry is not opted in. Run: php artisan flowform:telemetry opt-in');

            return self::SUCCESS;
        }

        $result = $telemetry->ping();

        if ($result->sent) {
            $this->info('Telemetry ping sent successfully.');

            return self::SUCCESS;
        }

        $this->warn('Telemetry ping failed. This is non-critical and will retry tomorrow.');

        return self::SUCCESS;
    }
}
