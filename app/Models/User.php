<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'is_admin',
    'plan',
    'monthly_quota',
    'credits_balance',
    'payment_reference_id',
    'subscription_status',
    'subscription_expires_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'monthly_quota' => 'integer',
            'credits_balance' => 'integer',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function isPro(): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if (in_array($this->plan, ['pro', 'team', 'enterprise'], true)) {
            if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
                return false;
            }
            return true;
        }

        return false;
    }

    public function currentMonthSubmissionsCount(): int
    {
        return Submission::whereHas('form', fn ($q) => $q->where('user_id', $this->id))
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function canReceiveSubmissions(bool $hasEmail = false): bool
    {
        if ($this->isPro()) {
            return true;
        }

        $quota = $this->monthly_quota ?? 50;
        if ($this->currentMonthSubmissionsCount() < $quota) {
            return true;
        }

        // Pay-As-You-Go Credits Check:
        // 5 credits for Amazon SES email forwarding, 1 credit for webhook / database only
        $cost = $hasEmail ? 5 : 1;
        return ($this->credits_balance ?? 0) >= $cost;
    }

    public function deductCredits(bool $hasEmail = false): void
    {
        if ($this->is_admin || $this->isPro()) {
            return;
        }

        $quota = $this->monthly_quota ?? 50;
        if ($this->currentMonthSubmissionsCount() <= $quota) {
            return; // Covered under free monthly quota
        }

        $cost = $hasEmail ? 5 : 1;
        if (($this->credits_balance ?? 0) >= $cost) {
            $this->decrement('credits_balance', $cost);
        }
    }

    public function estimatedEmailSubmissions(): int
    {
        return (int) floor(($this->credits_balance ?? 0) / 5);
    }

    public function estimatedWebhookSubmissions(): int
    {
        return (int) ($this->credits_balance ?? 0);
    }
}
