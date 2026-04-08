<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function records()
    {
        return $this->hasMany(EntityRecord::class);
    }
}
