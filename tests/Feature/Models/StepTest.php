<?php

use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Illuminate\Database\UniqueConstraintViolationException;

test('can create a step', function () {
    $form = Form::create(['name' => 'Test Form']);

    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Personal Info',
        'description' => 'Enter your personal information',
    ]);

    expect($step)->toBeInstanceOf(Step::class)
        ->and($step->title)->toBe('Personal Info')
        ->and($step->description)->toBe('Enter your personal information');
});

test('step belongs to form', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);

    expect($step->form)->not->toBeNull()
        ->and($step->form->id)->toBe($form->id);
});

test('step has fields relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'order' => 1,
    ]);

    expect($step->fields)->toHaveCount(1)
        ->and($step->fields->first()->code)->toBe('name');
});

test('casts meta as array', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step',
        'meta' => ['icon' => 'user'],
    ]);

    expect($step->meta)->toBeArray()
        ->and($step->meta)->toBe(['icon' => 'user']);
});

test('casts validation_rules as array', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step',
        'validation_rules' => ['required_fields' => ['name', 'email']],
    ]);

    expect($step->validation_rules)->toBeArray()
        ->and($step->validation_rules)->toBe(['required_fields' => ['name', 'email']]);
});

test('casts is_visible as boolean', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step',
        'is_visible' => true,
    ]);

    expect($step->is_visible)->toBeTrue();
});

test('unique constraint on form_id and step_number', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);

    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Duplicate']);
})->throws(UniqueConstraintViolationException::class);

test('steps are ordered by step_number on form', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 3, 'title' => 'Third']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    $titles = $form->steps->pluck('title')->toArray();

    expect($titles)->toBe(['First', 'Second', 'Third']);
});

test('nextStep returns the next step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);
    Step::create(['form_id' => $form->id, 'step_number' => 3, 'title' => 'Third']);

    expect($step1->nextStep()->id)->toBe($step2->id)
        ->and($step1->nextStep()->title)->toBe('Second');
});

test('nextStep returns null on last step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Last']);

    expect($step2->nextStep())->toBeNull();
});

test('previousStep returns the previous step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    expect($step2->previousStep()->id)->toBe($step1->id)
        ->and($step2->previousStep()->title)->toBe('First');
});

test('previousStep returns null on first step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);

    expect($step1->previousStep())->toBeNull();
});

test('isFirstStep returns true for first step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    expect($step1->isFirstStep())->toBeTrue();
});

test('isFirstStep returns false for non-first step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    expect($step2->isFirstStep())->toBeFalse();
});

test('isLastStep returns true for last step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Last']);

    expect($step2->isLastStep())->toBeTrue();
});

test('isLastStep returns false for non-last step', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    expect($step1->isLastStep())->toBeFalse();
});

test('getIcon returns icon from meta', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step',
        'meta' => ['icon' => 'user-circle'],
    ]);

    expect($step->getIcon())->toBe('user-circle');
});

test('getIcon returns null when no icon set', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step',
    ]);

    expect($step->getIcon())->toBeNull();
});

test('deleting a step cascades to fields', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'name',
        'label' => 'Name',
        'order' => 1,
    ]);

    expect(Field::count())->toBe(1);

    $step->delete();

    expect(Field::count())->toBe(0);
});
