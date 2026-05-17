<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Field;
use App\Models\Submission;
use Illuminate\Support\Collection;

class ConditionResolver
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
    ) {}

    public function resolve(Field $field, Submission $submission): FieldState
    {
        $conditions = $field->conditions;

        if ($conditions->isEmpty()) {
            return new FieldState(
                isVisible: true,
                isRequired: $field->is_required,
            );
        }

        $valueMap = $this->buildValueMap($submission, $conditions->pluck('depends_on_field_id')->unique());

        // Pass 1: resolve visibility
        $isVisible = true;
        foreach ($conditions->whereIn('action', ['show', 'hide']) as $condition) {
            $submissionValue = $valueMap[$condition->depends_on_field_id] ?? null;
            $met = $this->evaluator->evaluate($condition, $submissionValue);

            if ($met && $condition->action === 'hide') {
                $isVisible = false;
            } elseif ($met && $condition->action === 'show') {
                $isVisible = true;
            }
        }

        // Pass 2: resolve required (only when visible)
        $isRequired = $field->is_required;
        if ($isVisible) {
            foreach ($conditions->where('action', 'require') as $condition) {
                $submissionValue = $valueMap[$condition->depends_on_field_id] ?? null;
                if ($this->evaluator->evaluate($condition, $submissionValue)) {
                    $isRequired = true;
                }
            }
        }

        return new FieldState(isVisible: $isVisible, isRequired: $isRequired);
    }

    private function buildValueMap(Submission $submission, Collection $fieldIds): array
    {
        return $submission->values
            ->whereIn('field_id', $fieldIds)
            ->pluck('value', 'field_id')
            ->all();
    }
}
