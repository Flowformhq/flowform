<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    protected $fillable = [
        'form_id',
        'step_id',
        'field_type_id',
        'code',
        'label',
        'placeholder',
        'description',
        'is_required',
        'is_repeatable',
        'default_value',
        'validation_rules',
        'config',
        'order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_repeatable' => 'boolean',
        'validation_rules' => 'array',
        'config' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function step()
    {
        return $this->belongsTo(Step::class);
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }

    public function options()
    {
        return $this->hasMany(FieldOption::class)->orderBy('order');
    }

    public function conditions()
    {
        return $this->hasMany(Condition::class)->orderBy('id');
    }

    public function dependsOnConditions()
    {
        return $this->hasMany(Condition::class, 'depends_on_field_id');
    }

    public function submissionValues()
    {
        return $this->hasMany(SubmissionValue::class);
    }
}
