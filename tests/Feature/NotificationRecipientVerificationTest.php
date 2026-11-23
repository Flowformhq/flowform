<?php

declare(strict_types=1);

use App\Filament\Resources\Forms\Pages\EditForm;
use App\Mail\SubmissionReceived;
use App\Mail\VerifyNotificationRecipient;
use App\Models\Form;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

test('saving a form sends a verification email to unverified notification addresses', function () {
    $form = Form::create([
        'name' => 'Lead Gen Form',
        'slug' => 'lead-gen',
        'type' => 'endpoint',
        'notification_email' => 'sales@example.com, marketing@example.com',
        'is_active' => true,
    ]);

    expect($form->recipients)->toHaveCount(2);

    $recipient = NotificationRecipient::where('form_id', $form->id)->where('email', 'sales@example.com')->first();
    expect($recipient)->not->toBeNull();
    expect($recipient->isVerified())->toBeFalse();

    Mail::assertQueued(VerifyNotificationRecipient::class, 2);
});

test('submitting a form with unverified notification email sends NO submission email to that address', function () {
    $form = Form::create([
        'name' => 'Unconfirmed Form',
        'slug' => 'unconfirmed',
        'type' => 'endpoint',
        'notification_email' => 'unverified@example.com',
        'is_active' => true,
    ]);

    // Clear the verification email queue from creation
    Mail::fake();

    $response = $this->postJson('/api/v1/f/unconfirmed', [
        'name' => 'Alice Lead',
        'email' => 'alice@client.com',
    ]);

    $response->assertOk();

    // SubmissionReceived must NOT be sent to unverified recipient
    Mail::assertNotQueued(SubmissionReceived::class);
    Mail::assertNothingSent();
});

test('clicking valid signed verification link marks the recipient verified and shows confirmation page', function () {
    $form = Form::create([
        'name' => 'Signed Form',
        'slug' => 'signed-form',
        'type' => 'endpoint',
        'notification_email' => 'pending@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'pending@example.com')->first();
    expect($recipient->isVerified())->toBeFalse();

    $signedUrl = URL::temporarySignedRoute(
        'notifications.verify',
        now()->addDays(7),
        ['recipient' => $recipient->id]
    );

    $this->get($signedUrl)
        ->assertOk()
        ->assertViewIs('notifications.verified')
        ->assertSee('pending@example.com')
        ->assertSee('Yes, send me submissions');

    expect($recipient->fresh()->isVerified())->toBeFalse();

    $this->post($signedUrl)->assertOk()->assertSee('Notification Email Verified');

    expect($recipient->fresh()->isVerified())->toBeTrue();
});

test('re-saving a form does not resend verification emails to pending recipients', function () {
    $form = Form::create([
        'name' => 'Resave Form',
        'slug' => 'resave-form',
        'type' => 'endpoint',
        'notification_email' => 'victim@example.com',
        'is_active' => true,
    ]);

    $form->update(['is_active' => false]);
    $form->update(['is_active' => true]);
    $form->update(['notification_email' => 'victim@example.com, new@example.com']);

    Mail::assertQueued(VerifyNotificationRecipient::class, 2);
    Mail::assertQueued(VerifyNotificationRecipient::class, fn ($mail) => $mail->hasTo('victim@example.com'));
    Mail::assertQueued(VerifyNotificationRecipient::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

test('subsequent submissions email the verified recipient', function () {
    $form = Form::create([
        'name' => 'Verified Form',
        'slug' => 'verified-form',
        'type' => 'endpoint',
        'notification_email' => 'confirmed@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'confirmed@example.com')->first();
    $recipient->markAsVerified();

    Mail::fake();

    $response = $this->postJson('/api/v1/f/verified-form', [
        'name' => 'Bob Lead',
        'email' => 'bob@client.com',
    ]);

    $response->assertOk();

    Mail::assertQueued(SubmissionReceived::class, function ($mail) {
        return $mail->hasTo('confirmed@example.com');
    });
});

test('address matching verified form owner email is verified immediately without sending confirmation email', function () {
    $user = User::factory()->create([
        'email' => 'owner@example.com',
        'email_verified_at' => now(),
    ]);

    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Owner Form',
        'slug' => 'owner-form',
        'type' => 'endpoint',
        'notification_email' => 'owner@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'owner@example.com')->first();
    expect($recipient)->not->toBeNull();
    expect($recipient->isVerified())->toBeTrue();

    // No verification email should be queued for owner
    Mail::assertNotQueued(VerifyNotificationRecipient::class);
});

test('tampered or expired signed verification URLs return 403', function () {
    $form = Form::create([
        'name' => 'Tamper Form',
        'slug' => 'tamper-form',
        'type' => 'endpoint',
        'notification_email' => 'tamper@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->first();

    // Expired URL
    $expiredUrl = URL::temporarySignedRoute(
        'notifications.verify',
        now()->subMinute(),
        ['recipient' => $recipient->id]
    );

    $this->get($expiredUrl)->assertForbidden();

    // Tampered URL
    $validUrl = URL::temporarySignedRoute(
        'notifications.verify',
        now()->addDays(7),
        ['recipient' => $recipient->id]
    );
    $tamperedUrl = $validUrl.'&extra=tamper';

    $this->get($tamperedUrl)->assertForbidden();
    $this->post($tamperedUrl)->assertForbidden();
    $this->post(route('notifications.verify.confirm', ['recipient' => $recipient->id]))->assertForbidden();

    expect($recipient->fresh()->isVerified())->toBeFalse();
});

test('form edit page renders recipient verification statuses', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::create([
        'user_id' => $admin->id,
        'name' => 'Edit Page Form',
        'slug' => 'edit-page-form',
        'type' => 'endpoint',
        'notification_email' => 'pending@example.com',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertOk()
        ->assertSee('pending@example.com');
});

test('resend verification action queues one mail and a second within 10 minutes queues nothing and shows error', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::create([
        'user_id' => $admin->id,
        'name' => 'Resend Action Form',
        'slug' => 'resend-action-form',
        'type' => 'endpoint',
        'notification_email' => 'pending@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'pending@example.com')->first();
    expect($recipient->isVerified())->toBeFalse();

    Mail::fake();

    $this->actingAs($admin);

    // 1. First resend queues one verification mail
    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertActionVisible("resend_verification_{$recipient->id}")
        ->callAction("resend_verification_{$recipient->id}")
        ->assertNotified();

    Mail::assertQueued(VerifyNotificationRecipient::class, 1);
    Mail::assertQueued(VerifyNotificationRecipient::class, fn ($m) => $m->hasTo('pending@example.com'));

    // Clear mail queue
    Mail::fake();

    // 2. Second resend within 10 minutes queues nothing and shows an error
    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction("resend_verification_{$recipient->id}")
        ->assertNotified('Rate limit exceeded');

    Mail::assertNotQueued(VerifyNotificationRecipient::class);
    Mail::assertNothingOutgoing();
});

test('verified recipients do not show the resend verification action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::create([
        'user_id' => $admin->id,
        'name' => 'Verified Form',
        'slug' => 'verified-form',
        'type' => 'endpoint',
        'notification_email' => 'confirmed@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'confirmed@example.com')->first();
    $recipient->markAsVerified();

    $this->actingAs($admin);

    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->assertActionDoesNotExist("resend_verification_{$recipient->id}");
});

test('resend verification action enforces maximum 5 resends per day', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::create([
        'user_id' => $admin->id,
        'name' => 'Daily Limit Form',
        'slug' => 'daily-limit-form',
        'type' => 'endpoint',
        'notification_email' => 'daily@example.com',
        'is_active' => true,
    ]);

    $recipient = $form->recipients()->where('email', 'daily@example.com')->first();

    Mail::fake();
    $this->actingAs($admin);

    $key10m = "resend_verification_10m:{$recipient->id}";

    // Execute 5 resends, clearing the 10-minute window in between
    for ($i = 1; $i <= 5; $i++) {
        RateLimiter::clear($key10m);

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->callAction("resend_verification_{$recipient->id}")
            ->assertNotified();
    }

    Mail::assertQueued(VerifyNotificationRecipient::class, 5);

    Mail::fake();
    RateLimiter::clear($key10m);

    // 6th resend within the same day must be blocked by the daily limit
    Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
        ->callAction("resend_verification_{$recipient->id}")
        ->assertNotified('Rate limit exceeded');

    Mail::assertNotQueued(VerifyNotificationRecipient::class);
    Mail::assertNothingOutgoing();
});
