<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}
