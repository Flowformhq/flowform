<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates Sanctum bearer tokens (or Sanctum::actingAs test sessions) when present and verifies the required ability, while leaving unauthenticated requests untouched.
 */
class OptionalSanctumAbility
{
    /**
     * @throws AuthenticationException
     * @throws MissingAbilityException
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $guard = Auth::guard('sanctum');

        if ($request->bearerToken() !== null) {
            $user = $guard->user();
            if ($user === null) {
                throw new AuthenticationException('Unauthenticated.');
            }

            $this->authorizeAbility($request, $user, $ability);

            return $next($request);
        }

        if ($guard->hasUser()) {
            $user = $guard->user();
            /** @var mixed $token */
            $token = $user?->currentAccessToken();

            if ($user !== null && $token !== null && ! ($token instanceof TransientToken)) {
                $this->authorizeAbility($request, $user, $ability);
            }
        }

        return $next($request);
    }

    /**
     * @throws MissingAbilityException
     */
    protected function authorizeAbility(Request $request, mixed $user, string $ability): void
    {
        $request->setUserResolver(fn () => $user);

        if (! $user->tokenCan($ability)) {
            throw new MissingAbilityException($ability);
        }
    }
}
