<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ConditionEvaluator;
use App\Services\ConditionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Submission extends Model
{
    protected $fillable = [
        'form_id',
        'uuid',
        'status',
        'is_encrypted',
        'current_step',
        'data',
        'meta',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Transparently decrypt AES-256 encrypted submission data on access.
     */
    public function getDataAttribute($value): ?array
    {
        if (empty($value)) {
            return [];
        }

        if ($this->is_encrypted) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($value);
                return json_decode($decrypted, true) ?: [];
            } catch (\Throwable $e) {
                return ['_encrypted' => true, '_notice' => 'Encrypted PII at rest (AES-256)'];
            }
        }

        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Transparently encrypt data payload with AES-256 before saving to database.
     */
    public function setDataAttribute($value): void
    {
        $shouldEncrypt = $this->is_encrypted || ($this->relationLoaded('form') && $this->form?->is_encrypted);

        if ($shouldEncrypt) {
            $this->attributes['is_encrypted'] = true;
            $json = is_string($value) ? $value : json_encode($value);
            $this->attributes['data'] = \Illuminate\Support\Facades\Crypt::encryptString($json);
        } else {
            $this->attributes['data'] = is_array($value) ? json_encode($value) : $value;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (Submission $submission) {
            if (empty($submission->uuid)) {
                $submission->uuid = Str::uuid()->toString();
            }
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }

    public function entityRecords(): HasMany
    {
        return $this->hasMany(EntityRecord::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function currentStep(): ?Step
    {
        return Step::where('form_id', $this->form_id)
            ->where('step_number', $this->current_step)
            ->first();
    }

    public function advanceStep(): bool
    {
        $current = $this->currentStep();
        if (! $current || $current->isLastStep()) {
            return false;
        }

        $next = $current->nextStep();
        $this->update(['current_step' => $next->step_number]);

        return true;
    }

    public function retreatStep(): bool
    {
        $current = $this->currentStep();
        if (! $current || $current->isFirstStep()) {
            return false;
        }

        $previous = $current->previousStep();
        $this->update(['current_step' => $previous->step_number]);

        return true;
    }

    public function isOnLastStep(): bool
    {
        $current = $this->currentStep();

        return $current ? $current->isLastStep() : false;
    }

    public function progressPercentage(): int
    {
        $totalSteps = $this->form->stepCount();
        if ($totalSteps === 0) {
            return 0;
        }

        return (int) round(($this->current_step / $totalSteps) * 100);
    }

    public function evaluateConditions(): array
    {
        $this->loadMissing(['form.fields.conditions', 'values']);

        $resolver = new ConditionResolver(new ConditionEvaluator);
        $result = [];

        foreach ($this->form->fields as $field) {
            $result[$field->id] = $resolver->resolve($field, $this);
        }

        return $result;
    }
}
