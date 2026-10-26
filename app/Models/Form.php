<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'is_active',
        'version',
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
                $form->access_key = 'ff_' . strtolower(Str::random(10));
            }
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name) ?: ('form-' . substr($form->uuid, 0, 8));
            }
        });
    }

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
                if ($host === $rootDomain || str_ends_with($host, '.' . $rootDomain)) {
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
