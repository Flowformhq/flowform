<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Step extends Model
{
    protected $fillable = [
        'form_id',
        'step_number',
        'title',
        'description',
        'meta',
        'validation_rules',
        'is_visible',
    ];

    protected $casts = [
        'meta' => 'array',
        'validation_rules' => 'array',
        'is_visible' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('order');
    }

    public function nextStep(): ?self
    {
        return self::where('form_id', $this->form_id)
            ->where('step_number', '>', $this->step_number)
            ->orderBy('step_number')
            ->first();
    }

    public function previousStep(): ?self
    {
        return self::where('form_id', $this->form_id)
            ->where('step_number', '<', $this->step_number)
            ->orderByDesc('step_number')
            ->first();
    }

    public function isFirstStep(): bool
    {
        return ! self::where('form_id', $this->form_id)
            ->where('step_number', '<', $this->step_number)
            ->exists();
    }

    public function isLastStep(): bool
    {
        return ! self::where('form_id', $this->form_id)
            ->where('step_number', '>', $this->step_number)
            ->exists();
    }

    public function getIcon(): ?string
    {
        return $this->meta['icon'] ?? null;
    }

    public function setIcon(string $icon): void
    {
        $this->meta = array_merge($this->meta ?? [], ['icon' => $icon]);
        $this->save();
    }
}
