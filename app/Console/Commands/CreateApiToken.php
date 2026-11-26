<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    protected $signature = 'token:create {name=astro-client} {email?} {--abilities= : Comma-separated token abilities (defaults to wildcard *)} {--expires= : Token expiration in days (positive integer; defaults to no expiry)}';

    protected $description = 'Create a Sanctum API token for headless client authentication';

    public function handle(ApiTokenService $tokenService): int
    {
        $email = $this->argument('email');
        $user = $email ? User::where('email', $email)->first() : User::first();

        if (! $user) {
            $this->error('No user found to create a token for. Please run migrations/seeders first.');

            return self::FAILURE;
        }

        $abilities = $tokenService->parseAbilitiesOption($this->option('abilities'));
        if ($abilities === null) {
            $this->error('Invalid abilities provided. Valid values: '.implode(', ', $tokenService->allowedAbilityValues()));

            return self::FAILURE;
        }

        $expiresAt = $tokenService->parseExpiresDaysOption($this->option('expires'));
        if ($expiresAt === false) {
            $this->error('Invalid --expires value. Provide a positive whole number of days (e.g. --expires=30).');

            return self::FAILURE;
        }

        $tokenName = (string) $this->argument('name');
        $token = $user->createToken($tokenName, $abilities, $expiresAt)->plainTextToken;

        $expirySummary = $expiresAt ? $expiresAt->toIso8601String() : 'never';
        $this->info("Token created successfully for {$user->email} (abilities: ".implode(', ', $abilities).", expires: {$expirySummary}):");
        $this->line($token);

        return self::SUCCESS;
    }
}
