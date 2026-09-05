<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateApiToken extends Command
{
    protected $signature = 'token:create {name=astro-client} {email?}';

    protected $description = 'Create a Sanctum API token for headless client authentication';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = $email ? User::where('email', $email)->first() : User::first();

        if (! $user) {
            $this->error('No user found to create a token for. Please run migrations/seeders first.');
            return self::FAILURE;
        }

        $tokenName = (string) $this->argument('name');
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->info("Token created successfully for {$user->email}:");
        $this->line($token);

        return self::SUCCESS;
    }
}
