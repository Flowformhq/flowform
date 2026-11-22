<?php

declare(strict_types=1);

use App\Mail\SubmissionReceived;
use App\Mail\VerifyNotificationRecipient;
use App\Models\Form;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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

    $response = $this->get($signedUrl);
    $response->assertOk()
        ->assertViewIs('notifications.verified')
        ->assertSee('pending@example.com');

    expect($recipient->fresh()->isVerified())->toBeTrue();
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
});
