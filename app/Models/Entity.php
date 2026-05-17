<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    protected $fillable = [
        'form_id',
        'name',
        'label',
        'is_repeatable',
    ];

    protected $casts = [
        'is_repeatable' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(EntityRecord::class);
    }
}
