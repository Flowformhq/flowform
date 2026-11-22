<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\VerifyNotificationRecipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $fillable = [
        'user_id',
        'uuid',
        'access_key',
        'name',
        'slug',
        'type',
        'description',
        'notification_email',
        'redirect_url',
        'allowed_domains',
        'is_encrypted',
        'encrypted_fields',
        'webhook_url',
        'webhook_secret',
        'captcha_provider',
        'captcha_secret',
        'captcha_site_key',
        'is_active',
        'version',
    ];

    protected $hidden = [
        'captcha_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_encrypted' => 'boolean',
        'encrypted_fields' => 'array',
        'captcha_secret' => 'encrypted',
    ];

    protected $attributes = [
        'type' => 'wizard',
    ];

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            if (empty($form->uuid)) {
                $form->uuid = Str::uuid()->toString();
            }
            if (empty($form->access_key)) {
                $form->access_key = 'ff_'.strtolower(Str::random(10));
            }
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) ?: ('form-'.substr($form->uuid, 0, 8));
            }
        });

        static::saved(function (Form $form) {
            $form->syncNotificationRecipients();
        });
    }

    /**
     * @return HasMany<NotificationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function syncNotificationRecipients(): void
    {
        if (empty($this->notification_email)) {
            $this->recipients()->delete();

            return;
        }

        $emails = array_unique(array_filter(array_map('trim', explode(',', strtolower($this->notification_email)))));

        $this->recipients()->whereNotIn('email', $emails)->delete();

        $owner = $this->user;
        $ownerEmail = $owner && $owner->email_verified_at ? strtolower($owner->email) : null;

        foreach ($emails as $email) {
            $recipient = $this->recipients()->firstOrNew(['email' => $email]);

            if ($recipient->isVerified()) {
                continue;
            }

            if ($ownerEmail && $email === $ownerEmail) {
                $recipient->verified_at = now();
                $recipient->save();

                continue;
            }

            if ($recipient->exists) {
                continue;
            }

            $recipient->save();

            try {
                Mail::to($email)->send(new VerifyNotificationRecipient($recipient));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('step_number');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('order');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEndpoint($query)
    {
        return $query->where('type', 'endpoint');
    }

    public function scopeWizard($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'wizard')->orWhereNull('type');
        });
    }

    public function isEndpoint(): bool
    {
        return $this->type === 'endpoint';
    }

    public function isWizard(): bool
    {
        return $this->type === 'wizard' || empty($this->type);
    }

    public function hasCaptcha(): bool
    {
        return ! empty($this->captcha_provider);
    }

    public function verifyCaptcha(?string $token, ?string $remoteIp = null): bool
    {
        if (empty($token) || ! $this->hasCaptcha() || empty($this->captcha_secret)) {
            return false;
        }

        $endpoint = match ($this->captcha_provider) {
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
            default => null,
        };

        if (! $endpoint) {
            return false;
        }

        try {
            $params = [
                'secret' => $this->captcha_secret,
                'response' => $token,
            ];

            if ($remoteIp) {
                $params['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()->timeout(5)->post($endpoint, $params);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Check if a given request origin or referer is permitted by allowed_domains.
     */
    public function isAllowedOrigin(?string $origin): bool
    {
        if (empty($this->allowed_domains)) {
            return true; // No domain restrictions configured
        }

        if (empty($origin)) {
            return true; // Direct API or cURL requests without Origin
        }

        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;
        $host = strtolower(trim((string) $host));

        $allowed = array_filter(array_map('trim', explode(',', strtolower($this->allowed_domains))));

        foreach ($allowed as $pattern) {
            if ($pattern === '*' || $pattern === $host) {
                return true;
            }

            // Wildcard matching e.g. *.myportfolio.com
            if (str_starts_with($pattern, '*.')) {
                $rootDomain = substr($pattern, 2);
                if ($host === $rootDomain || str_ends_with($host, '.'.$rootDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function firstStep(): ?Step
    {
        return $this->steps()->first();
    }

    public function stepCount(): int
    {
        return $this->steps()->count();
    }

    public function reorderSteps(array $stepIds): void
    {
        DB::transaction(function () use ($stepIds) {
            foreach ($stepIds as $index => $stepId) {
                Step::where('id', $stepId)
                    ->where('form_id', $this->id)
                    ->update(['step_number' => 10000 + $index]);
            }

            foreach ($stepIds as $index => $stepId) {
                Step::where('id', $stepId)
                    ->where('form_id', $this->id)
                    ->update(['step_number' => $index + 1]);
            }
        });
    }
}
