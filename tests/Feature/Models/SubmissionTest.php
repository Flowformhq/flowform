<?php

use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\SubmissionValue;

test('can create a submission', function () {
    $form = Form::create(['name' => 'Test Form']);
    $submission = Submission::create(['form_id' => $form->id]);

    expect($submission)->toBeInstanceOf(Submission::class)
        ->and($submission->form_id)->toBe($form->id)
        ->and($submission->fresh()->status)->toBe('draft');
});

test('uuid is auto-generated on creation', function () {
    $form = Form::create(['name' => 'Test Form']);
    $submission = Submission::create(['form_id' => $form->id]);

    expect($submission->uuid)->not->toBeNull()
        ->and($submission->uuid)->toBeString()
        ->and(strlen($submission->uuid))->toBe(36);
});

test('uuid is not overwritten if explicitly set', function () {
    $form = Form::create(['name' => 'Test Form']);
    $fixedUuid = '550e8400-e29b-41d4-a716-446655440000';
    $submission = Submission::create([
        'form_id' => $form->id,
        'uuid' => $fixedUuid,
    ]);

    expect($submission->uuid)->toBe($fixedUuid);
});

test('submission belongs to form', function () {
    $form = Form::create(['name' => 'Test Form']);
    $submission = Submission::create(['form_id' => $form->id]);

    expect($submission->form)->not->toBeNull()
        ->and($submission->form->id)->toBe($form->id);
});

test('submission has values relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id, 'field_type_id' => $fieldType->id,
        'code' => 'name', 'label' => 'Name', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);
    SubmissionValue::create([
        'submission_id' => $submission->id,
        'field_id' => $field->id,
        'value' => 'test value',
    ]);

    expect($submission->values)->toHaveCount(1)
        ->and($submission->values->first()->value)->toBe('test value');
});

test('submission has entity records relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $entity = Entity::create([
        'form_id' => $form->id,
        'name' => 'customer',
        'label' => 'Customer',
    ]);
    $submission = Submission::create(['form_id' => $form->id]);
    EntityRecord::create([
        'submission_id' => $submission->id,
        'entity_id' => $entity->id,
    ]);

    expect($submission->entityRecords)->toHaveCount(1)
        ->and($submission->entityRecords->first()->entity_id)->toBe($entity->id);
});

test('draft scope returns only draft submissions', function () {
    $form = Form::create(['name' => 'Test Form']);
    Submission::create(['form_id' => $form->id, 'status' => 'draft']);
    Submission::create(['form_id' => $form->id, 'status' => 'completed']);
    Submission::create(['form_id' => $form->id, 'status' => 'draft']);

    $drafts = Submission::draft()->get();

    expect($drafts)->toHaveCount(2)
        ->and($drafts->every(fn ($s) => $s->status === 'draft'))->toBeTrue();
});

test('completed scope returns only completed submissions', function () {
    $form = Form::create(['name' => 'Test Form']);
    Submission::create(['form_id' => $form->id, 'status' => 'draft']);
    Submission::create(['form_id' => $form->id, 'status' => 'completed']);
    Submission::create(['form_id' => $form->id, 'status' => 'completed']);

    $completed = Submission::completed()->get();

    expect($completed)->toHaveCount(2)
        ->and($completed->every(fn ($s) => $s->status === 'completed'))->toBeTrue();
});
