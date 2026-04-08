<?php

use App\Models\Condition;
use App\Services\ConditionEvaluator;

test('equals returns true when values match', function () {
    $condition = new Condition(['operator' => 'equals', 'value' => 'yes']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'yes'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'no'))->toBeFalse();
});

test('not_equals returns true when values differ', function () {
    $condition = new Condition(['operator' => 'not_equals', 'value' => 'yes']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'no'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'yes'))->toBeFalse();
});

test('contains returns true when substring is present', function () {
    $condition = new Condition(['operator' => 'contains', 'value' => 'foo']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'foobar'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'bar'))->toBeFalse();
});

test('greater_than returns true when submission value is numerically greater', function () {
    $condition = new Condition(['operator' => 'greater_than', 'value' => '10']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, '11'))->toBeTrue()
        ->and($evaluator->evaluate($condition, '10'))->toBeFalse()
        ->and($evaluator->evaluate($condition, '9'))->toBeFalse();
});

test('greater_than returns false for non-numeric values', function () {
    $condition = new Condition(['operator' => 'greater_than', 'value' => '10']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'abc'))->toBeFalse()
        ->and($evaluator->evaluate($condition, null))->toBeFalse();
});

test('less_than returns true when submission value is numerically less', function () {
    $condition = new Condition(['operator' => 'less_than', 'value' => '10']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, '9'))->toBeTrue()
        ->and($evaluator->evaluate($condition, '10'))->toBeFalse()
        ->and($evaluator->evaluate($condition, '11'))->toBeFalse();
});

test('less_than returns false for non-numeric values', function () {
    $condition = new Condition(['operator' => 'less_than', 'value' => '10']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'abc'))->toBeFalse()
        ->and($evaluator->evaluate($condition, null))->toBeFalse();
});

test('in returns true when value is in the list', function () {
    $condition = new Condition(['operator' => 'in', 'value' => 'yes,no,maybe']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'yes'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'no'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'other'))->toBeFalse();
});

test('in handles spaces around commas', function () {
    $condition = new Condition(['operator' => 'in', 'value' => 'yes , no , maybe']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'yes'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'no'))->toBeTrue();
});

test('in returns false for empty list', function () {
    $condition = new Condition(['operator' => 'in', 'value' => '']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'yes'))->toBeFalse();
});

test('not_in returns true when value is not in the list', function () {
    $condition = new Condition(['operator' => 'not_in', 'value' => 'yes,no']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'maybe'))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'yes'))->toBeFalse();
});

test('empty returns true for null and empty string', function () {
    $condition = new Condition(['operator' => 'empty', 'value' => null]);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, null))->toBeTrue()
        ->and($evaluator->evaluate($condition, ''))->toBeTrue()
        ->and($evaluator->evaluate($condition, 'something'))->toBeFalse();
});

test('not_empty returns true for non-empty string', function () {
    $condition = new Condition(['operator' => 'not_empty', 'value' => null]);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'something'))->toBeTrue()
        ->and($evaluator->evaluate($condition, null))->toBeFalse()
        ->and($evaluator->evaluate($condition, ''))->toBeFalse();
});

test('unknown operator returns false without exception', function () {
    $condition = new Condition(['operator' => 'unknown_operator', 'value' => 'test']);
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate($condition, 'test'))->toBeFalse();
});
