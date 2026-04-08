<?php

use App\Models\Condition;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;

test('can create a condition', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_a', 'label' => 'Field A', 'order' => 1,
    ]);
    $dependsOn = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_b', 'label' => 'Field B', 'order' => 2,
    ]);

    $condition = Condition::create([
        'field_id' => $field->id,
        'depends_on_field_id' => $dependsOn->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'show',
    ]);

    expect($condition)->toBeInstanceOf(Condition::class)
        ->and($condition->operator)->toBe('equals')
        ->and($condition->value)->toBe('yes')
        ->and($condition->action)->toBe('show');
});

test('condition belongs to field', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_a', 'label' => 'Field A', 'order' => 1,
    ]);
    $dependsOn = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_b', 'label' => 'Field B', 'order' => 2,
    ]);

    $condition = Condition::create([
        'field_id' => $field->id,
        'depends_on_field_id' => $dependsOn->id,
        'operator' => 'not_empty',
        'action' => 'show',
    ]);

    expect($condition->field)->not->toBeNull()
        ->and($condition->field->id)->toBe($field->id);
});

test('condition belongs to depends on field', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_a', 'label' => 'Field A', 'order' => 1,
    ]);
    $dependsOn = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_b', 'label' => 'Field B', 'order' => 2,
    ]);

    $condition = Condition::create([
        'field_id' => $field->id,
        'depends_on_field_id' => $dependsOn->id,
        'operator' => 'equals',
        'value' => 'test',
        'action' => 'hide',
    ]);

    expect($condition->dependsOnField)->not->toBeNull()
        ->and($condition->dependsOnField->id)->toBe($dependsOn->id);
});

test('condition stores operator and value and action', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'target', 'label' => 'Target', 'order' => 1,
    ]);
    $dependsOn = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'trigger', 'label' => 'Trigger', 'order' => 2,
    ]);

    $condition = Condition::create([
        'field_id' => $field->id,
        'depends_on_field_id' => $dependsOn->id,
        'operator' => 'contains',
        'value' => 'hello',
        'action' => 'disable',
    ]);

    expect($condition->operator)->toBe('contains')
        ->and($condition->value)->toBe('hello')
        ->and($condition->action)->toBe('disable');
});
