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

test('POST /api/v1/public/submissions creates a new guest submission without auth', function () {
    $form = Form::create(['name' => 'Public Quiz', 'is_active' => true]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);

    $this->postJson('/api/v1/public/submissions', ['form_uuid' => $form->uuid])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.current_step', 1);
});

test('POST /api/v1/public/submissions/{uuid}/values stores values without auth', function () {
    $form = Form::create(['name' => 'Public Form', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'answer', 'label' => 'Answer', 'order' => 1,
    ]);
    $submission = Submission::create(['form_id' => $form->id]);

    $this->postJson("/api/v1/public/submissions/{$submission->uuid}/values", [
        'values' => [
            ['field_code' => 'answer', 'value' => 'Option B'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.values.answer', 'Option B');
});

test('cross-user tenancy authorization: User B receives 404 when attempting to access or modify User A submission', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $formA = Form::create([
        'user_id' => $userA->id,
        'name' => 'User A Confidential Form',
        'is_active' => true,
    ]);
    $step = Step::create(['form_id' => $formA->id, 'step_number' => 1, 'title' => 'Step 1']);
    $step2 = Step::create(['form_id' => $formA->id, 'step_number' => 2, 'title' => 'Step 2']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $formA->id, 'step_id' => $step->id,
        'field_type_id' => $ft->id, 'code' => 'ssn', 'label' => 'SSN', 'order' => 1,
    ]);

    $submissionA = Submission::create([
        'form_id' => $formA->id,
        'user_id' => $userA->id,
        'status' => 'draft',
        'current_step' => 1,
    ]);

    // 1. User B GET /submissions/{uuid} -> 404
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/submissions/{$submissionA->uuid}")
        ->assertNotFound();

    // 2. User B PATCH /submissions/{uuid} -> 404
    $this->actingAs($userB, 'sanctum')
        ->patchJson("/api/v1/submissions/{$submissionA->uuid}", ['status' => 'completed'])
        ->assertNotFound();

    // 3. User B POST /submissions/{uuid}/values -> 404
    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/submissions/{$submissionA->uuid}/values", [
            'values' => [['field_code' => 'ssn', 'value' => '999-99-9999']],
        ])
        ->assertNotFound();

    // 4. User B POST /submissions/{uuid}/advance -> 404
    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/submissions/{$submissionA->uuid}/advance")
        ->assertNotFound();

    // 5. User B POST /submissions/{uuid}/retreat -> 404
    $this->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/submissions/{$submissionA->uuid}/retreat")
        ->assertNotFound();

    // 6. User B GET /submissions/{uuid}/conditions -> 404
    $this->actingAs($userB, 'sanctum')
        ->getJson("/api/v1/submissions/{$submissionA->uuid}/conditions")
        ->assertNotFound();

    // Verification: User A CAN access their own submission
    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/submissions/{$submissionA->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $submissionA->uuid);
});

test('POST /api/v1/public/submissions returns 422 when form is inactive', function () {
    $form = Form::create(['name' => 'Inactive Form', 'is_active' => false]);
    Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);

    $this->postJson('/api/v1/public/submissions', ['form_uuid' => $form->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['form_uuid']);
});

test('guest mutating routes on public submissions return 422 when form is inactive', function () {
    $form = Form::create(['name' => 'Deactivating Form', 'is_active' => true]);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Step 2']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step1->id,
        'field_type_id' => $ft->id, 'code' => 'answer', 'label' => 'Answer', 'order' => 1,
    ]);

    $submission = Submission::create([
        'form_id' => $form->id,
        'status' => 'draft',
        'current_step' => 1,
    ]);

    // Deactivate the form
    $form->update(['is_active' => false]);

    // 1. Guest POST public/submissions/{uuid}/values returns 422
    $this->postJson("/api/v1/public/submissions/{$submission->uuid}/values", [
        'values' => [['field_code' => 'answer', 'value' => 'New Answer']],
    ])->assertStatus(422);

    // 2. Guest POST public/submissions/{uuid}/advance returns 422
    $this->postJson("/api/v1/public/submissions/{$submission->uuid}/advance")
        ->assertStatus(422);

    // 3. Guest PATCH public/submissions/{uuid} returns 422
    $this->patchJson("/api/v1/public/submissions/{$submission->uuid}", [
        'status' => 'completed',
    ])->assertStatus(422);
});

test('authenticated owner can still update a draft whose form is inactive via authenticated route', function () {
    $user = User::factory()->create();
    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Owner Inactive Form',
        'is_active' => false,
    ]);
    $step1 = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $step2 = Step::create(['form_id' => $form->id, 'step_number' => 2, 'title' => 'Step 2']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id, 'step_id' => $step1->id,
        'field_type_id' => $ft->id, 'code' => 'note', 'label' => 'Note', 'order' => 1,
    ]);

    $submission = Submission::create([
        'form_id' => $form->id,
        'user_id' => $user->id,
        'status' => 'draft',
        'current_step' => 1,
    ]);

    // Owner can update status via authenticated route
    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/submissions/{$submission->uuid}", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    // Owner can store values via authenticated route
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/values", [
            'values' => [['field_code' => 'note', 'value' => 'Admin override']],
        ])
        ->assertOk()
        ->assertJsonPath('data.values.note', 'Admin override');

    // Owner can advance step via authenticated route
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/submissions/{$submission->uuid}/advance")
        ->assertOk()
        ->assertJsonPath('current_step', 2);
});
