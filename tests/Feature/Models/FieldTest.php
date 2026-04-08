<?php

use App\Models\Condition;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;

test('can create a field', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'email_address',
        'label' => 'Email Address',
        'placeholder' => 'Enter your email',
        'order' => 1,
    ]);

    expect($field)->toBeInstanceOf(Field::class)
        ->and($field->code)->toBe('email_address')
        ->and($field->label)->toBe('Email Address');
});

test('casts validation_rules as array', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'validation_rules' => ['required', 'string', 'max:255'],
        'order' => 1,
    ]);

    expect($field->validation_rules)->toBeArray()
        ->and($field->validation_rules)->toBe(['required', 'string', 'max:255']);
});

test('casts config as array', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'age',
        'label' => 'Age',
        'config' => ['min' => 0, 'max' => 120],
        'order' => 1,
    ]);

    expect($field->config)->toBeArray()
        ->and($field->config)->toBe(['min' => 0, 'max' => 120]);
});

test('casts is_required as boolean', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'is_required' => true,
        'order' => 1,
    ]);

    expect($field->is_required)->toBeTrue();
});

test('casts is_repeatable as boolean', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'items',
        'label' => 'Items',
        'is_repeatable' => true,
        'order' => 1,
    ]);

    expect($field->is_repeatable)->toBeTrue();
});

test('field belongs to form', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'order' => 1,
    ]);

    expect($field->form)->not->toBeNull()
        ->and($field->form->id)->toBe($form->id);
});

test('field belongs to step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'order' => 1,
    ]);

    expect($field->step)->not->toBeNull()
        ->and($field->step->id)->toBe($step->id);
});

test('field belongs to field type', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'email', 'component' => 'email-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'email',
        'label' => 'Email',
        'order' => 1,
    ]);

    expect($field->fieldType)->not->toBeNull()
        ->and($field->fieldType->id)->toBe($fieldType->id)
        ->and($field->fieldType->name)->toBe('email');
});

test('field has options relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'select', 'component' => 'select-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'color',
        'label' => 'Color',
        'order' => 1,
    ]);
    FieldOption::create([
        'field_id' => $field->id,
        'label' => 'Red',
        'value' => 'red',
        'order' => 1,
    ]);
    FieldOption::create([
        'field_id' => $field->id,
        'label' => 'Blue',
        'value' => 'blue',
        'order' => 2,
    ]);

    expect($field->options)->toHaveCount(2)
        ->and($field->options->first()->label)->toBe('Red');
});

test('field has conditions relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    $fieldA = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_a', 'label' => 'Field A', 'order' => 1,
    ]);
    $fieldB = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'field_b', 'label' => 'Field B', 'order' => 2,
    ]);

    Condition::create([
        'field_id' => $fieldA->id,
        'depends_on_field_id' => $fieldB->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'show',
    ]);

    expect($fieldA->conditions)->toHaveCount(1)
        ->and($fieldA->conditions->first()->operator)->toBe('equals');
});
