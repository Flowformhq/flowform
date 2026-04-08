<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldOption extends Model
{
    protected $fillable = [
        'field_id',
        'label',
        'value',
        'order',
    ];

    public function field()
    {
        return $this->belongsTo(Field::class);
    }
}
