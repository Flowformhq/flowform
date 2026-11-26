<?php

declare(strict_types=1);

use App\Filament\Pages\MailSettingsPage;
use App\Filament\Resources\FieldTypes\FieldTypeResource;
use App\Filament\Resources\Steps\Pages\CreateStep;
use App\Filament\Resources\Steps\Pages\ListSteps;
use App\Filament\Resources\Steps\StepResource;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\User;
use Livewire\Livewire;

test('only admins can open the mail settings page', function () {
    $this->actingAs(User::factory()->create())->get(MailSettingsPage::getUrl())->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(MailSettingsPage::getUrl())->assertOk();
});

test('non-admins cannot save mail settings through Livewire', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(MailSettingsPage::class)->assertForbidden();
});

test('only admins can manage field types', function () {
    $this->actingAs(User::factory()->create())->get(FieldTypeResource::getUrl())->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(FieldTypeResource::getUrl())->assertOk();
});

test('steps are scoped to the owner of their form', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $otherForm = Form::create(['user_id' => $other->id, 'name' => 'Other', 'slug' => 'other-steps', 'type' => 'endpoint', 'is_active' => true]);
    $otherStep = Step::create(['form_id' => $otherForm->id, 'step_number' => 1, 'title' => 'Private step']);

    $this->actingAs($owner);

    Livewire::test(ListSteps::class)->assertCanNotSeeTableRecords([$otherStep]);
    $this->get(StepResource::getUrl('edit', ['record' => $otherStep]))->assertNotFound();

    Livewire::test(CreateStep::class)
        ->fillForm(['form_id' => $otherForm->id, 'step_number' => 2, 'title' => 'Injected'])
        ->call('create')
        ->assertHasFormErrors(['form_id']);

    expect(Step::where('title', 'Injected')->exists())->toBeFalse();
});

test('submissions on ownerless forms are visible only to admins in the panel', function () {
    $form = Form::create(['name' => 'Ownerless', 'slug' => 'ownerless-panel', 'type' => 'endpoint', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'status' => 'completed', 'data' => ['email' => 'ownerless@example.com']]);

    $this->actingAs(User::factory()->create());
    Livewire::test(ListSubmissions::class)->assertCanNotSeeTableRecords([$submission]);
    $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertNotFound();

    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(ListSubmissions::class)->assertCanSeeTableRecords([$submission]);
    $this->get(SubmissionResource::getUrl('view', ['record' => $submission]))->assertOk();
});
