<?php

declare(strict_types=1);

namespace App\Models;

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
}
