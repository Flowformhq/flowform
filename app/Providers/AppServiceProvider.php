<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Telemetry\TelemetryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelemetryService::class, function ($app) {
            return new TelemetryService(
                $app->make(HttpFactory::class),
                config('flowform.telemetry.endpoint'),
                config('flowform.telemetry.enabled'),
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('api-auth', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
