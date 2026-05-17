<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }

    public function fieldType(): BelongsTo
    {
        return $this->belongsTo(FieldType::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class)->orderBy('order');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(Condition::class)->orderBy('id');
    }

    public function dependsOnConditions(): HasMany
    {
        return $this->hasMany(Condition::class, 'depends_on_field_id');
    }

    public function submissionValues(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }
}
