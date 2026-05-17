<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Condition;

class ConditionEvaluator
{
    public function evaluate(Condition $condition, ?string $submissionValue): bool
    {
        return match ($condition->operator) {
            'equals' => $submissionValue === $condition->value,
            'not_equals' => $submissionValue !== $condition->value,
            'contains' => str_contains((string) $submissionValue, (string) $condition->value),
            'greater_than' => $this->compareNumeric($submissionValue, $condition->value, '>'),
            'less_than' => $this->compareNumeric($submissionValue, $condition->value, '<'),
            'in' => in_array($submissionValue, $this->parseList($condition->value), true),
            'not_in' => ! in_array($submissionValue, $this->parseList($condition->value), true),
            'empty' => $submissionValue === null || $submissionValue === '',
            'not_empty' => $submissionValue !== null && $submissionValue !== '',
            default => false,
        };
    }

    private function compareNumeric(?string $a, ?string $b, string $op): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return $op === '>' ? (float) $a > (float) $b : (float) $a < (float) $b;
    }

    private function parseList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }
}
