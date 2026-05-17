<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'is_active',
        'version',
    ];

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            if (empty($form->uuid)) {
                $form->uuid = Str::uuid()->toString();
            }
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
        });
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('step_number');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class)->orderBy('order');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function firstStep(): ?Step
    {
        return $this->steps()->first();
    }

    public function stepCount(): int
    {
        return $this->steps()->count();
    }

    public function reorderSteps(array $stepIds): void
    {
        DB::transaction(function () use ($stepIds) {
            // Temporarily set step_numbers to high values to avoid unique constraint conflicts
            foreach ($stepIds as $index => $stepId) {
                Step::where('id', $stepId)
                    ->where('form_id', $this->id)
                    ->update(['step_number' => 10000 + $index]);
            }

            // Now set the actual step_numbers
            foreach ($stepIds as $index => $stepId) {
                Step::where('id', $stepId)
                    ->where('form_id', $this->id)
                    ->update(['step_number' => $index + 1]);
            }
        });
    }
}
