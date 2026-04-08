<?php

use App\Models\Entity;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;

test('can create a form', function () {
    $form = Form::create([
        'name' => 'Contact Form',
        'description' => 'A simple contact form',
    ]);

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->name)->toBe('Contact Form')
        ->and($form->description)->toBe('A simple contact form');
});

test('uuid is auto-generated on creation', function () {
    $form = Form::create([
        'name' => 'Test Form',
    ]);

    expect($form->uuid)->not->toBeNull()
        ->and($form->uuid)->toBeString()
        ->and(strlen($form->uuid))->toBe(36);
});

test('slug is auto-generated from name', function () {
    $form = Form::create([
        'name' => 'My Contact Form',
    ]);

    expect($form->slug)->toBe('my-contact-form');
});

test('slug is not overwritten if explicitly set', function () {
    $form = Form::create([
        'name' => 'My Contact Form',
        'slug' => 'custom-slug',
    ]);

    expect($form->slug)->toBe('custom-slug');
});

test('active scope returns only active forms', function () {
    Form::create(['name' => 'Active Form', 'is_active' => true]);
    Form::create(['name' => 'Inactive Form', 'is_active' => false]);
    Form::create(['name' => 'Another Active', 'is_active' => true]);

    $activeForms = Form::active()->get();

    expect($activeForms)->toHaveCount(2)
        ->and($activeForms->pluck('name')->toArray())->toBe(['Active Form', 'Another Active']);
});

test('form has steps relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    Step::create([
        'form_id' => $form->id,
        'step_number' => 1,
        'title' => 'Step One',
    ]);

    expect($form->steps)->toHaveCount(1)
        ->and($form->steps->first()->title)->toBe('Step One');
});

test('form has fields relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step']);
    $fieldType = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $fieldType->id,
        'code' => 'first_name',
        'label' => 'First Name',
        'order' => 1,
    ]);

    expect($form->fields)->toHaveCount(1)
        ->and($form->fields->first()->label)->toBe('First Name');
});

test('form has entities relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    Entity::create([
        'form_id' => $form->id,
        'name' => 'customer',
        'label' => 'Customer',
    ]);

    expect($form->entities)->toHaveCount(1)
        ->and($form->entities->first()->name)->toBe('customer');
});

test('form has submissions relationship', function () {
    $form = Form::create(['name' => 'Test Form']);
    Submission::create(['form_id' => $form->id]);
    Submission::create(['form_id' => $form->id]);

    expect($form->submissions)->toHaveCount(2);
});
