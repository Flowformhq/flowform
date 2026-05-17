<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Condition extends Model
{
    protected $fillable = [
        'field_id',
        'depends_on_field_id',
        'operator',
        'value',
        'action',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function dependsOnField(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'depends_on_field_id');
    }
}
