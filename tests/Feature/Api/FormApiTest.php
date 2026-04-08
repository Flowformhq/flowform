<?php

use App\Models\Field;
use App\Models\FieldOption;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;

test('GET /api/v1/forms returns only active forms', function () {
    Form::create(['name' => 'Active Form', 'is_active' => true]);
    Form::create(['name' => 'Inactive Form', 'is_active' => false]);

    $this->getJson('/api/v1/forms')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Form');
});

test('GET /api/v1/forms returns paginated response', function () {
    Form::create(['name' => 'Form A', 'is_active' => true]);
    Form::create(['name' => 'Form B', 'is_active' => true]);

    $this->getJson('/api/v1/forms')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

test('GET /api/v1/forms/{uuid} returns a form by uuid', function () {
    $form = Form::create(['name' => 'Test Form', 'is_active' => true]);

    $this->getJson("/api/v1/forms/{$form->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $form->uuid)
        ->assertJsonPath('data.name', 'Test Form');
});

test('GET /api/v1/forms/{uuid} returns 404 for unknown uuid', function () {
    $this->getJson('/api/v1/forms/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

test('GET /api/v1/forms/{slug}/by-slug returns a form by slug', function () {
    Form::create(['name' => 'Contact Us', 'slug' => 'contact-us', 'is_active' => true]);

    $this->getJson('/api/v1/forms/contact-us/by-slug')
        ->assertOk()
        ->assertJsonPath('data.slug', 'contact-us');
});

test('GET /api/v1/forms/{uuid}/schema returns nested steps and fields', function () {
    $form = Form::create(['name' => 'Schema Test', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step One']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $ft->id,
        'code' => 'email',
        'label' => 'Email',
        'order' => 1,
    ]);
    FieldOption::create(['field_id' => $field->id, 'label' => 'Option A', 'value' => 'a', 'order' => 1]);

    $this->getJson("/api/v1/forms/{$form->uuid}/schema")
        ->assertOk()
        ->assertJsonPath('data.uuid', $form->uuid)
        ->assertJsonCount(1, 'data.steps')
        ->assertJsonPath('data.steps.0.title', 'Step One')
        ->assertJsonPath('data.steps.0.fields.0.code', 'email')
        ->assertJsonCount(1, 'data.steps.0.fields.0.options');
});

test('form endpoints are accessible without a bearer token', function () {
    $form = Form::create(['name' => 'Public Form', 'is_active' => true]);

    $this->getJson('/api/v1/forms')->assertOk();
    $this->getJson("/api/v1/forms/{$form->uuid}")->assertOk();
    $this->getJson("/api/v1/forms/{$form->uuid}/schema")->assertOk();
});
