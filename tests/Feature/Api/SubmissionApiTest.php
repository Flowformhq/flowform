<?php

use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\User;

test('POST /api/v1/submissions creates a new submission', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/submissions', ['form_uuid' => $form->uuid])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.current_step', 1);
});

test('POST /api/v1/submissions requires authentication', function () {
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);

    $this->postJson('/api/v1/submissions', ['form_uuid' => $form->uuid])
        ->assertUnauthorized();
});

test('POST /api/v1/submissions returns 422 for unknown form_uuid', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/submissions', ['form_uuid' => 'does-not-exist'])
        ->assertUnprocessable();
});

test('GET /api/v1/submissions/{uuid} returns submission with values map', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'name', 'label' => 'Name', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);
    $submission->values()->create(['field_id' => $field->id, 'value' => 'Alice']);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/submissions/{$submission->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $submission->uuid)
        ->assertJsonPath('data.values.name', 'Alice');
});

test('GET /api/v1/submissions/{uuid} requires authentication', function () {
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->getJson("/api/v1/submissions/{$submission->uuid}")
        ->assertUnauthorized();
});

test('PATCH /api/v1/submissions/{uuid} updates status', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/submissions/{$submission->uuid}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

test('POST /api/v1/submissions/{uuid}/values upserts field values', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'email', 'label' => 'Email', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/values", [
            'values' => [
                ['field_code' => 'email', 'value' => 'test@example.com'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.values.email', 'test@example.com');

    expect($submission->fresh()->values)->toHaveCount(1);
});

test('POST /api/v1/submissions/{uuid}/values upserts on second call', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'My Form', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'email', 'label' => 'Email', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/values", [
            'values' => [['field_code' => 'email', 'value' => 'first@example.com']],
        ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/values", [
            'values' => [['field_code' => 'email', 'value' => 'updated@example.com']],
        ])
        ->assertOk()
        ->assertJsonPath('data.values.email', 'updated@example.com');

    // Should still be 1 row, not 2
    expect($submission->fresh()->values)->toHaveCount(1);
});

test('POST /api/v1/submissions/{uuid}/advance advances the step', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'Multi-step Form', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Step 2']);
    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 1]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/advance")
        ->assertOk()
        ->assertJsonPath('current_step', 2);
});

test('POST /api/v1/submissions/{uuid}/advance returns 422 on last step', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'Single-step Form', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Only Step']);
    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 1]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/advance")
        ->assertUnprocessable();
});

test('POST /api/v1/submissions/{uuid}/retreat retreats the step', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'Multi-step Form', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Step 2']);
    $submission = Submission::create(['form_id' => $form->id, 'current_step' => 2]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/retreat")
        ->assertOk()
        ->assertJsonPath('current_step', 1);
});

test('GET /api/v1/submissions/{uuid}/conditions returns field states', function () {
    $user = User::factory()->create();
    $form = Form::create(['name' => 'Conditional Form', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'q1', 'label' => 'Q1', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/submissions/{$submission->uuid}/conditions")
        ->assertOk()
        ->assertJsonStructure(['data' => [['field_id', 'field_code', 'is_visible', 'is_required']]]);
});
