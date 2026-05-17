<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldOption extends Model
{
    protected $fillable = [
        'field_id',
        'label',
        'value',
        'order',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
