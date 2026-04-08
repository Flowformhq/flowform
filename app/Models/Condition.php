<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    protected $fillable = [
        'field_id',
        'depends_on_field_id',
        'operator',
        'value',
        'action',
    ];

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function dependsOnField()
    {
        return $this->belongsTo(Field::class, 'depends_on_field_id');
    }
}
