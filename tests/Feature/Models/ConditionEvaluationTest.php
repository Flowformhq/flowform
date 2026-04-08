<?php

use App\Models\Condition;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Services\FieldState;

function setupForm(): array
{
    $form = Form::create(['name' => 'Evaluation Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    return [$form, $step, $fieldType];
}

function createField(Form $form, Step $step, FieldType $ft, string $code, array $attrs = []): Field
{
    return Field::create(array_merge([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $ft->id,
        'code' => $code,
        'label' => ucfirst($code),
        'order' => 1,
    ], $attrs));
}

test('evaluateConditions returns array keyed by field id', function () {
    [$form, $step, $ft] = setupForm();
    createField($form, $step, $ft, 'field_a');
    createField($form, $step, $ft, 'field_b');

    $submission = Submission::create(['form_id' => $form->id]);
    $result = $submission->evaluateConditions();

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2);

    foreach ($result as $fieldId => $state) {
        expect($fieldId)->toBeInt()
            ->and($state)->toBeInstanceOf(FieldState::class);
    }
});

test('all fields default to visible with no conditions', function () {
    [$form, $step, $ft] = setupForm();
    createField($form, $step, $ft, 'field_a');
    createField($form, $step, $ft, 'field_b');

    $submission = Submission::create(['form_id' => $form->id]);
    $result = $submission->evaluateConditions();

    foreach ($result as $state) {
        expect($state->isVisible)->toBeTrue();
    }
});

test('end to end hide evaluation hides field when condition is met', function () {
    [$form, $step, $ft] = setupForm();
    $fieldA = createField($form, $step, $ft, 'field_a');
    $fieldB = createField($form, $step, $ft, 'field_b');

    Condition::create([
        'field_id' => $fieldB->id,
        'depends_on_field_id' => $fieldA->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $fieldA->id, 'value' => 'yes']);

    $result = $submission->evaluateConditions();

    expect($result[$fieldA->id]->isVisible)->toBeTrue()
        ->and($result[$fieldB->id]->isVisible)->toBeFalse();
});

test('end to end require evaluation makes field required when condition is met', function () {
    [$form, $step, $ft] = setupForm();
    $fieldA = createField($form, $step, $ft, 'field_a');
    $fieldB = createField($form, $step, $ft, 'field_b', ['is_required' => false]);

    Condition::create([
        'field_id' => $fieldB->id,
        'depends_on_field_id' => $fieldA->id,
        'operator' => 'not_empty',
        'value' => null,
        'action' => 'require',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $fieldA->id, 'value' => 'provided']);

    $result = $submission->evaluateConditions();

    expect($result[$fieldB->id]->isRequired)->toBeTrue();
});

test('multiple fields are resolved independently', function () {
    [$form, $step, $ft] = setupForm();
    $fieldA = createField($form, $step, $ft, 'field_a');
    $fieldB = createField($form, $step, $ft, 'field_b');
    $fieldC = createField($form, $step, $ft, 'field_c');

    Condition::create([
        'field_id' => $fieldB->id,
        'depends_on_field_id' => $fieldA->id,
        'operator' => 'equals',
        'value' => 'hide_b',
        'action' => 'hide',
    ]);
    Condition::create([
        'field_id' => $fieldC->id,
        'depends_on_field_id' => $fieldA->id,
        'operator' => 'equals',
        'value' => 'hide_c',
        'action' => 'hide',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $fieldA->id, 'value' => 'hide_b']);

    $result = $submission->evaluateConditions();

    expect($result[$fieldA->id]->isVisible)->toBeTrue()
        ->and($result[$fieldB->id]->isVisible)->toBeFalse()  // condition met
        ->and($result[$fieldC->id]->isVisible)->toBeTrue();  // condition not met
});
