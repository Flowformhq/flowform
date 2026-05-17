<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldType extends Model
{
    protected $fillable = [
        'name',
        'component',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }
}
