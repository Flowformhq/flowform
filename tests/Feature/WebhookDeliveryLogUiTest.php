<?php

declare(strict_types=1);

use App\Filament\Pages\WebhooksPage;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Resources\Forms\Pages\EditForm;
use App\Filament\Resources\Forms\RelationManagers\WebhookDeliveriesRelationManager;
use App\Jobs\DeliverWebhook;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('relation manager shows only form deliveries to owner and filters by status', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create(['is_admin' => false]);

    $aliceForm = Form::create(['user_id' => $alice->id, 'name' => 'Alice Form', 'type' => 'endpoint', 'is_active' => true, 'webhook_url' => 'https://alice.example.com/hook']);
    $bobForm = Form::create(['user_id' => $bob->id, 'name' => 'Bob Form', 'type' => 'endpoint', 'is_active' => true, 'webhook_url' => 'https://bob.example.com/hook']);

    $aliceSub = Submission::create(['form_id' => $aliceForm->id, 'status' => 'completed', 'data' => ['ok' => true]]);
    $bobSub = Submission::create(['form_id' => $bobForm->id, 'status' => 'completed', 'data' => ['ok' => true]]);

    $succeeded = WebhookDelivery::create([
        'form_id' => $aliceForm->id,
        'submission_id' => $aliceSub->id,
        'delivery_key' => "{$aliceSub->id}:legacy",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
        'response_code' => 200,
        'response_body' => 'ok',
        'duration_ms' => 42,
        'attempted_at' => now(),
    ]);

    $failed = WebhookDelivery::create([
        'form_id' => $aliceForm->id,
        'submission_id' => $aliceSub->id,
        'delivery_key' => "{$aliceSub->id}:sub1",
        'event' => 'submission.created',
        'attempt' => 2,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'response_body' => 'server err',
        'duration_ms' => 120,
        'attempted_at' => now(),
    ]);

    $bobDelivery = WebhookDelivery::create([
        'form_id' => $bobForm->id,
        'submission_id' => $bobSub->id,
        'delivery_key' => "{$bobSub->id}:legacy",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 502,
        'attempted_at' => now(),
    ]);

    $this->actingAs($alice);

    Livewire::test(WebhookDeliveriesRelationManager::class, [
        'ownerRecord' => $aliceForm,
        'pageClass' => EditForm::class,
    ])
        ->assertCanSeeTableRecords([$succeeded, $failed])
        ->assertCanNotSeeTableRecords([$bobDelivery])
        ->filterTable('status', WebhookDelivery::STATUS_FAILED)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$succeeded]);
});

test('non-admin cannot access other user form or ownerless form deliveries, while admin can', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $bobForm = Form::create(['user_id' => $bob->id, 'name' => 'Bob Private', 'type' => 'endpoint', 'is_active' => true]);
    $ownerlessForm = Form::create(['user_id' => null, 'name' => 'Orphan Form', 'type' => 'endpoint', 'is_active' => true]);

    $bobSub = Submission::create(['form_id' => $bobForm->id, 'status' => 'completed']);
    $orphanSub = Submission::create(['form_id' => $ownerlessForm->id, 'status' => 'completed']);

    $bobDelivery = WebhookDelivery::create([
        'form_id' => $bobForm->id,
        'submission_id' => $bobSub->id,
        'delivery_key' => "{$bobSub->id}:legacy",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'attempted_at' => now(),
    ]);

    $orphanDelivery = WebhookDelivery::create([
        'form_id' => $ownerlessForm->id,
        'submission_id' => $orphanSub->id,
        'delivery_key' => "{$orphanSub->id}:legacy",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'attempted_at' => now(),
    ]);

    $this->actingAs($alice);

    $this->get(FormResource::getUrl('edit', ['record' => $bobForm]))->assertNotFound();
    $this->get(FormResource::getUrl('edit', ['record' => $ownerlessForm]))->assertNotFound();

    expect(WebhookDeliveriesRelationManager::canViewForRecord($bobForm, EditForm::class))->toBeFalse()
        ->and(WebhookDeliveriesRelationManager::canViewForRecord($ownerlessForm, EditForm::class))->toBeFalse();

    Livewire::test(WebhooksPage::class)
        ->assertDontSee("{$orphanSub->id}:legacy")
        ->assertDontSee('Orphan Form');

    $this->actingAs($admin);

    $this->get(FormResource::getUrl('edit', ['record' => $ownerlessForm]))->assertOk();
    expect(WebhookDeliveriesRelationManager::canViewForRecord($ownerlessForm, EditForm::class))->toBeTrue();

    Livewire::test(WebhookDeliveriesRelationManager::class, [
        'ownerRecord' => $ownerlessForm,
        'pageClass' => EditForm::class,
    ])->assertCanSeeTableRecords([$orphanDelivery]);

    Livewire::test(WebhooksPage::class)
        ->assertSee('Orphan Form');
});

test('manual retry enqueues DeliverWebhook once for failed deliveries, blocks duplicate success, and enforces per-delivery rate limit', function () {
    Queue::fake();
    RateLimiter::clear('webhook-retry');

    $owner = User::factory()->create(['is_admin' => false]);
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Retry UI Form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);
    $subscription = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://hooks.example.com/retry',
        'events' => ['submission.created'],
        'source' => 'n8n',
        'is_active' => true,
    ]);
    $submission = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'data' => ['email' => 'retry-ui@example.com'],
    ]);

    $failed = WebhookDelivery::create([
        'webhook_subscription_id' => $subscription->id,
        'form_id' => $form->id,
        'submission_id' => $submission->id,
        'delivery_key' => "{$submission->id}:{$subscription->id}",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'attempted_at' => now(),
    ]);

    $secondFailed = WebhookDelivery::create([
        'webhook_subscription_id' => $subscription->id,
        'form_id' => $form->id,
        'submission_id' => $submission->id,
        'delivery_key' => "{$submission->id}:second",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 503,
        'attempted_at' => now(),
    ]);

    $alreadySucceeded = WebhookDelivery::create([
        'webhook_subscription_id' => $subscription->id,
        'form_id' => $form->id,
        'submission_id' => $submission->id,
        'delivery_key' => "{$submission->id}:done",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
        'response_code' => 200,
        'attempted_at' => now(),
    ]);

    $this->actingAs($owner);

    $component = Livewire::test(WebhookDeliveriesRelationManager::class, [
        'ownerRecord' => $form,
        'pageClass' => EditForm::class,
    ]);

    // First manual retry -> enqueues DeliverWebhook once
    $component->callTableAction('retry', $failed)
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    Queue::assertPushed(DeliverWebhook::class, 1);

    // Immediate second click on same failed delivery -> rate limited (does not enqueue a second job)
    $component->callTableAction('retry', $failed);
    Queue::assertPushed(DeliverWebhook::class, 1);

    // Another failed attempt row for the same delivery key shares the rate limit bucket
    $sameKeyLaterAttempt = WebhookDelivery::create([
        'webhook_subscription_id' => $subscription->id,
        'form_id' => $form->id,
        'submission_id' => $submission->id,
        'delivery_key' => "{$submission->id}:{$subscription->id}",
        'event' => 'submission.created',
        'attempt' => 2,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'attempted_at' => now(),
    ]);
    $component->callTableAction('retry', $sameKeyLaterAttempt);
    Queue::assertPushed(DeliverWebhook::class, 1);

    // Separate delivery has independent rate limit bucket
    $component->callTableAction('retry', $secondFailed);
    Queue::assertPushed(DeliverWebhook::class, 2);

    // Retry action is not available on succeeded deliveries
    $component->assertTableActionHidden('retry', $alreadySucceeded);
});

test('response body is shown truncated and escaped in delivery log views', function () {
    $owner = User::factory()->create(['is_admin' => false]);
    $form = Form::create(['user_id' => $owner->id, 'name' => 'XSS Form', 'type' => 'endpoint', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'status' => 'completed']);

    $xssBody = '<script>alert("xss")</script>'.str_repeat('A', 300);

    $delivery = WebhookDelivery::create([
        'form_id' => $form->id,
        'submission_id' => $submission->id,
        'delivery_key' => "{$submission->id}:legacy",
        'event' => 'submission.created',
        'attempt' => 1,
        'status' => WebhookDelivery::STATUS_FAILED,
        'response_code' => 500,
        'response_body' => $xssBody,
        'attempted_at' => now(),
    ]);

    $this->actingAs($owner);

    $component = Livewire::test(WebhookDeliveriesRelationManager::class, [
        'ownerRecord' => $form,
        'pageClass' => EditForm::class,
    ])
        ->assertCanSeeTableRecords([$delivery])
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);

    // Long body should be truncated in table display
    expect($component->html())->not->toContain(str_repeat('A', 200));

    // Also escaped on WebhooksPage recent deliveries
    Livewire::test(WebhooksPage::class)
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
});

test('webhooks page shows owner subscriptions with failed attempts label and disable/enable/delete actions', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create(['is_admin' => false]);

    $aliceForm = Form::create(['user_id' => $alice->id, 'name' => 'Alice Sales', 'type' => 'endpoint', 'is_active' => true]);
    $bobForm = Form::create(['user_id' => $bob->id, 'name' => 'Bob Private', 'type' => 'endpoint', 'is_active' => true]);

    $aliceSub = WebhookSubscription::create([
        'user_id' => $alice->id,
        'form_id' => null,
        'target_url' => 'https://hooks.alice.com/in',
        'source' => 'zapier',
        'events' => ['submission.created'],
        'failure_count' => 7,
        'is_active' => true,
    ]);

    $bobSub = WebhookSubscription::create([
        'user_id' => $bob->id,
        'form_id' => $bobForm->id,
        'target_url' => 'https://hooks.bob.com/in',
        'source' => 'n8n',
        'events' => ['submission.created'],
        'is_active' => true,
    ]);

    $this->actingAs($alice);

    $this->get(WebhooksPage::getUrl())->assertOk();

    Livewire::test(WebhooksPage::class)
        ->assertCanSeeTableRecords([$aliceSub])
        ->assertCanNotSeeTableRecords([$bobSub])
        ->assertSee('All forms')
        ->assertSee('Failed Attempts')
        ->callTableAction('disable', $aliceSub)
        ->assertHasNoTableActionErrors();

    expect($aliceSub->fresh()->is_active)->toBeFalse()
        ->and($aliceSub->fresh()->disabled_at)->not->toBeNull();

    Livewire::test(WebhooksPage::class)
        ->callTableAction('enable', $aliceSub)
        ->assertHasNoTableActionErrors();

    expect($aliceSub->fresh()->is_active)->toBeTrue()
        ->and($aliceSub->fresh()->disabled_at)->toBeNull()
        ->and($aliceSub->fresh()->failure_count)->toBe(0);

    // Alice cannot trigger actions on Bob's subscription
    expect(fn () => Livewire::test(WebhooksPage::class)->callTableAction('delete', $bobSub))
        ->toThrow(ActionNotResolvableException::class);

    Livewire::test(WebhooksPage::class)
        ->callTableAction('delete', $aliceSub)
        ->assertHasNoTableActionErrors();

    expect(WebhookSubscription::find($aliceSub->id))->toBeNull()
        ->and(WebhookSubscription::find($bobSub->id))->not->toBeNull();
});
