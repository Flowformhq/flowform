<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelemetryInstall;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Console\Command;

class TelemetryOptIn extends Command
{
    protected $signature = 'flowform:telemetry {action : opt-in or opt-out}';

    protected $description = 'Manage anonymous telemetry reporting';

    public function handle(TelemetryService $telemetry): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['opt-in', 'opt-out', 'status'])) {
            $this->error('Invalid action. Use: opt-in, opt-out, or status');

            return self::FAILURE;
        }

        if ($action === 'status') {
            $install = TelemetryInstall::getOrCreate();
            $this->info('Install ID: '.$install->install_id);
            $this->info('Opted in: '.($install->opted_in ? 'Yes' : 'No'));
            $this->info('Last ping: '.($install->last_ping_at?->toIso8601String() ?? 'Never'));

            return self::SUCCESS;
        }

        if ($action === 'opt-in') {
            $this->warn('FlowForm collects anonymous install-level metrics to understand adoption.');
            $this->warn('No PII, no form content, no user data is ever collected.');
            $this->warn('');
            $this->warn('Data collected: install ID, version, response volume bucket,');
            $this->warn('database driver, PHP version, enabled integrations.');
            $this->warn('');
            $this->warn('Set FLOWFORM_TELEMETRY_ENABLED=false in .env to disable completely.');

            if (! $this->confirm('Do you want to enable anonymous telemetry?', true)) {
                $this->info('Telemetry remains disabled.');

                return self::SUCCESS;
            }

            $telemetry->optIn();
            $this->info('Telemetry enabled. Thank you for helping improve FlowForm!');
            $this->info('You can opt out anytime with: php artisan flowform:telemetry opt-out');

            return self::SUCCESS;
        }

        $telemetry->optOut();
        $this->info('Telemetry disabled. No data will be sent.');

        return self::SUCCESS;
    }
}
