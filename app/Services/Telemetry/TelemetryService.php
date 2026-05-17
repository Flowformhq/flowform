<?php

declare(strict_types=1);

namespace App\Services\Telemetry;

use App\Models\Submission;
use App\Models\TelemetryInstall;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    public function __construct(
        private HttpFactory $http,
        private string $endpoint,
        private bool $enabled,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isOptedIn(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return TelemetryInstall::getOrCreate()->opted_in;
    }

    public function optIn(): void
    {
        TelemetryInstall::getOrCreate()->update(['opted_in' => true]);
    }

    public function optOut(): void
    {
        TelemetryInstall::getOrCreate()->update(['opted_in' => false]);
    }

    public function ping(): TelemetryResult
    {
        if (! $this->enabled || ! $this->isOptedIn()) {
            return TelemetryResult::skipped();
        }

        $install = TelemetryInstall::getOrCreate();

        $payload = $this->buildPayload($install);

        try {
            $response = $this->http
                ->timeout(5)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, $payload);

            $install->update(['last_ping_at' => now()]);

            return $response->successful()
                ? TelemetryResult::sent()
                : TelemetryResult::failed($response->status());
        } catch (\Throwable $e) {
            Log::debug('FlowForm telemetry ping failed', [
                'error' => $e->getMessage(),
            ]);

            return TelemetryResult::failed(0);
        }
    }

    private function buildPayload(TelemetryInstall $install): array
    {
        return [
            'install_id' => $install->install_id,
            'version' => $this->getAppVersion(),
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'database_driver' => config('database.default'),
            'response_volume_bucket' => $this->getResponseVolumeBucket(),
            'country' => $this->detectCountry(),
            'enabled_integrations' => $this->getEnabledIntegrations(),
            'pinged_at' => now()->toIso8601String(),
        ];
    }

    private function getAppVersion(): string
    {
        return config('flowform.version', '0.1.0');
    }

    private function getResponseVolumeBucket(): string
    {
        try {
            $count = Submission::where(
                'created_at',
                '>=',
                now()->subDays(30)
            )->count();

            return match (true) {
                $count === 0 => '0',
                $count < 100 => '1-99',
                $count < 1000 => '100-999',
                $count < 10000 => '1000-9999',
                default => '10000+',
            };
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function detectCountry(): ?string
    {
        return config('app.locale') === 'en' ? null : config('app.locale');
    }

    private function getEnabledIntegrations(): array
    {
        $integrations = [];

        if (config('services.postmark.key')) {
            $integrations[] = 'postmark';
        }
        if (config('services.resend.key')) {
            $integrations[] = 'resend';
        }
        if (config('services.slack.notifications.bot_user_oauth_token')) {
            $integrations[] = 'slack';
        }
        if (config('services.github.client_id')) {
            $integrations[] = 'github_oauth';
        }
        if (config('services.google.client_id')) {
            $integrations[] = 'google_oauth';
        }

        return $integrations;
    }
}
