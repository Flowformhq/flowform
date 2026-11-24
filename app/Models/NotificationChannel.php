<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannel extends Model
{
    public const TYPE_SLACK = 'slack';

    public const TYPE_DISCORD = 'discord';

    public const TYPE_TELEGRAM = 'telegram';

    public const TYPES = [
        self::TYPE_SLACK,
        self::TYPE_DISCORD,
        self::TYPE_TELEGRAM,
    ];

    protected $fillable = [
        'form_id',
        'type',
        'config',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'config' => 'encrypted:array',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
