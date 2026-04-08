<?php

use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;

test('submission currentStep returns correct step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    expect($submission->currentStep())->not->toBeNull()
        ->and($submission->currentStep()->id)->toBe($step2->id);
});

test('submission currentStep returns null when step not found', function () {
    $form = Form::create(['name' => 'Test Form']);
    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 99]);

    expect($submission->currentStep())->toBeNull();
});

test('submission can advance to next step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 1]);

    $result = $submission->advanceStep();

    expect($result)->toBeTrue()
        ->and($submission->fresh()->current_step)->toBe(2);
});

test('submission cannot advance past last step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Last']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    $result = $submission->advanceStep();

    expect($result)->toBeFalse()
        ->and($submission->fresh()->current_step)->toBe(2);
});

test('submission can retreat to previous step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    $result = $submission->retreatStep();

    expect($result)->toBeTrue()
        ->and($submission->fresh()->current_step)->toBe(1);
});

test('submission cannot retreat before first step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 1]);

    $result = $submission->retreatStep();

    expect($result)->toBeFalse()
        ->and($submission->fresh()->current_step)->toBe(1);
});

test('submission isOnLastStep works correctly', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Last']);

    $submission1 = Submission::create(['form_id' => $form->id, 'current_step' => 1]);
    $submission2 = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    expect($submission1->isOnLastStep())->toBeFalse()
        ->and($submission2->isOnLastStep())->toBeTrue();
});

test('submission progressPercentage calculates correctly', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);
    Step::create(['form_id' => $form->id, 'step_number' => 3, 'title' => 'Third']);

    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    expect($submission->progressPercentage())->toBe(67);
});

test('form firstStep returns first step', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);
    $first = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);

    expect($form->firstStep()->id)->toBe($first->id);
});

test('form stepCount returns correct count', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);
    Step::create(['form_id' => $form->id, 'step_number' => 3, 'title' => 'Third']);

    expect($form->stepCount())->toBe(3);
});

test('form reorderSteps updates step numbers', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'First']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Second']);
    $step3 = Step::create(['form_id' => $form->id, 'step_number' => 3, 'title' => 'Third']);

    // Reorder: Third becomes first, First becomes second, Second becomes third
    $form->reorderSteps([$step3->id, $step1->id, $step2->id]);

    expect($step3->fresh()->step_number)->toBe(1)
        ->and($step1->fresh()->step_number)->toBe(2)
        ->and($step2->fresh()->step_number)->toBe(3);
});
