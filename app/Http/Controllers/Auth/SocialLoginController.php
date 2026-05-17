<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialLoginController
{
    public function redirect(string $provider): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $socialiteUser = Socialite::driver($provider)->user();

        $user = $this->findOrCreateUser($provider, $socialiteUser);

        Auth::login($user, true);

        return redirect()->intended(config('filament.panels.admin.path', '/admin'));
    }

    protected function findOrCreateUser(string $provider, SocialiteUser $socialiteUser): User
    {
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($socialAccount) {
            $socialAccount->update([
                'token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'token_expires_at' => $socialiteUser->expiresIn
                    ? now()->addSeconds($socialiteUser->expiresIn)
                    : null,
            ]);

            return $socialAccount->user;
        }

        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
                'email' => $socialiteUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
            'token' => $socialiteUser->token,
            'refresh_token' => $socialiteUser->refreshToken,
            'token_expires_at' => $socialiteUser->expiresIn
                ? now()->addSeconds($socialiteUser->expiresIn)
                : null,
        ]);

        return $user;
    }

    protected function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['github', 'google'])) {
            abort(404, "Unsupported OAuth provider: {$provider}");
        }
    }
}
