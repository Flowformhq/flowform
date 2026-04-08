<?php

use App\Models\Condition;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\SubmissionValue;
use App\Services\ConditionEvaluator;
use App\Services\ConditionResolver;

function makeField(Form $form, Step $step, FieldType $fieldType, string $code, array $attrs = []): Field
{
    return Field::create(array_merge([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => $code,
        'label' => ucfirst($code),
        'order' => 1,
    ], $attrs));
}

function makeSetup(): array
{
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);

    return [$form, $step, $fieldType];
}

test('no conditions returns defaults', function () {
    [$form, $step, $ft] = makeSetup();
    $field = makeField($form, $step, $ft, 'name', ['is_required' => true]);
    $submission = Submission::create(['form_id' => $form->id]);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($field->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeTrue()
        ->and($state->isRequired)->toBeTrue();
});

test('hide condition met makes field invisible', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target');

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger->id, 'value' => 'yes']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeFalse();
});

test('hide condition not met leaves field visible', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target');

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger->id, 'value' => 'no']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeTrue();
});

test('show condition re-shows field after hide', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target');

    // Hide if 'no', show if 'yes'
    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'equals',
        'value' => 'no',
        'action' => 'hide',
    ]);
    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'show',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger->id, 'value' => 'yes']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeTrue();
});

test('require condition met makes field required', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target', ['is_required' => false]);

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'not_empty',
        'value' => null,
        'action' => 'require',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger->id, 'value' => 'something']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isRequired)->toBeTrue();
});

test('require condition not applied when field is hidden', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target', ['is_required' => false]);

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);
    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'not_empty',
        'value' => null,
        'action' => 'require',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger->id, 'value' => 'yes']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeFalse()
        ->and($state->isRequired)->toBeFalse();
});

test('missing submission value is treated as null', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger = makeField($form, $step, $ft, 'trigger');
    $target = makeField($form, $step, $ft, 'target');

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger->id,
        'operator' => 'empty',
        'value' => null,
        'action' => 'hide',
    ]);

    // No SubmissionValue for trigger field
    $submission = Submission::create(['form_id' => $form->id]);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    // empty operator on null → condition met → hide
    expect($state->isVisible)->toBeFalse();
});

test('any met hide condition hides field', function () {
    [$form, $step, $ft] = makeSetup();
    $trigger1 = makeField($form, $step, $ft, 'trigger1');
    $trigger2 = makeField($form, $step, $ft, 'trigger2');
    $target = makeField($form, $step, $ft, 'target');

    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger1->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);
    Condition::create([
        'field_id' => $target->id,
        'depends_on_field_id' => $trigger2->id,
        'operator' => 'equals',
        'value' => 'yes',
        'action' => 'hide',
    ]);

    $submission = Submission::create(['form_id' => $form->id]);
    // Only trigger2 matches
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger1->id, 'value' => 'no']);
    SubmissionValue::create(['submission_id' => $submission->id, 'field_id' => $trigger2->id, 'value' => 'yes']);

    $resolver = new ConditionResolver(new ConditionEvaluator);
    $state = $resolver->resolve($target->fresh(['conditions']), $submission->fresh(['values']));

    expect($state->isVisible)->toBeFalse();
});
