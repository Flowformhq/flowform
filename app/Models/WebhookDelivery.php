<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'webhook_subscription_id',
        'form_id',
        'submission_id',
        'delivery_key',
        'event',
        'attempt',
        'status',
        'response_code',
        'response_body',
        'duration_ms',
        'attempted_at',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'response_code' => 'integer',
        'duration_ms' => 'integer',
        'attempted_at' => 'datetime',
    ];

    /** @return BelongsTo<WebhookSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return self::applyVisibleTo($query, $user);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        return $query
            ->whereHas('form', fn (Builder $formQuery): Builder => $formQuery->where('user_id', $user->id))
            ->where(function (Builder $subQuery) use ($user): void {
                $subQuery->whereNull('webhook_subscription_id')
                    ->orWhereHas('subscription', fn (Builder $sq): Builder => $sq->where('user_id', $user->id));
            });
    }

    public function targetHost(): string
    {
        $subscription = $this->subscription;
        $form = $this->form;
        $url = '';

        if ($subscription !== null && filled($subscription->target_url)) {
            $url = (string) $subscription->target_url;
        } elseif ($form !== null && filled($form->webhook_url)) {
            $url = (string) $form->webhook_url;
        }

        if ($url === '') {
            return '—';
        }

        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }
}
