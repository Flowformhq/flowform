<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookSubscription extends Model
{
    public const SUPPORTED_EVENTS = [
        'submission.created',
    ];

    public const MAX_ACTIVE_PER_USER = 25;

    protected $fillable = [
        'user_id',
        'form_id',
        'uuid',
        'target_url',
        'secret',
        'events',
        'source',
        'is_active',
        'failure_count',
        'disabled_at',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
        'is_active' => 'boolean',
        'failure_count' => 'integer',
        'disabled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookSubscription $subscription): void {
            if (empty($subscription->uuid)) {
                $subscription->uuid = Str::uuid()->toString();
            }

            if (empty($subscription->secret)) {
                $subscription->secret = 'whsec_'.Str::random(32);
            }

            if (empty($subscription->events)) {
                $subscription->events = self::SUPPORTED_EVENTS;
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('disabled_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function targetHost(): string
    {
        return (string) (parse_url($this->target_url, PHP_URL_HOST) ?: $this->target_url);
    }

    public function supportsEvent(string $event): bool
    {
        return in_array($event, (array) ($this->events ?? []), true);
    }
}
