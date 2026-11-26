<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TokenAbility;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpFoundation\Response;

class EnsureComplianceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && auth('web')->check() && ! $request->user()?->currentAccessToken()) {
            return $next($request);
        }

        return app(CheckAbilities::class)->handle($request, $next, ...TokenAbility::values());
    }
}
