<?php

declare(strict_types=1);

use App\Filament\Pages\MailSettingsPage;
use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\MailSetting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    Mail::fake();
});

test('MailSetting encrypts password and api_key at rest and decrypts transparently', function () {
    $setting = MailSetting::create([
        'driver' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'testuser',
        'password' => 'secret_password_123',
        'api_key' => 're_test_api_key_456',
        'is_active' => true,
    ]);

    // Accessor decrypts
    expect($setting->password)->toEqual('secret_password_123');
    expect($setting->api_key)->toEqual('re_test_api_key_456');

    // Raw database value is encrypted ciphertext
    $raw = DB::table('mail_settings')->where('id', $setting->id)->first();
    expect($raw->password)->not->toEqual('secret_password_123');
    expect($raw->password)->not->toBeNull();
    expect(Crypt::decryptString($raw->password))->toEqual('secret_password_123');
});

test('MailSetting::applyActiveConfig updates Laravel mail configuration at runtime', function () {
    MailSetting::query()->delete();

    $setting = MailSetting::create([
        'driver' => 'smtp',
        'host' => 'mail.custom-relay.org',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'relay_user',
        'password' => 'relay_pass',
        'from_address' => 'forms@mybrand.com',
        'from_name' => 'Custom Brand Forms',
        'is_active' => true,
    ]);

    MailSetting::applyActiveConfig();

    expect(config('mail.default'))->toEqual('smtp');
    expect(config('mail.mailers.smtp.host'))->toEqual('mail.custom-relay.org');
    expect(config('mail.mailers.smtp.port'))->toEqual(465);
    expect(config('mail.mailers.smtp.encryption'))->toEqual('ssl');
    expect(config('mail.from.address'))->toEqual('forms@mybrand.com');
    expect(config('mail.from.name'))->toEqual('Custom Brand Forms');
});

test('MailSetting sendTestEmail successfully dispatches diagnostic test email', function () {
    $setting = MailSetting::create([
        'driver' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_address' => 'noreply@flowformhq.com',
        'is_active' => true,
    ]);

    $result = $setting->sendTestEmail('diagnostic-test@example.com');

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('diagnostic-test@example.com');
});

test('queued SubmissionReceived uses active MailSetting dynamic mailer and from-address at send time', function () {
    MailSetting::query()->delete();

    $setting = MailSetting::create([
        'driver' => 'smtp',
        'host' => 'smtp.queued-relay.com',
        'port' => 2525,
        'username' => 'queued_user',
        'password' => 'queued_secret',
        'from_address' => 'noreply@activebrand.com',
        'from_name' => 'Active Brand Alerts',
        'is_active' => true,
    ]);

    $form = Form::create([
        'name' => 'Contact Us',
        'slug' => 'contact-queue',
        'type' => 'endpoint',
        'notification_email' => 'admin@company.com',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['name' => 'Alice'],
    ]);

    $mailable = new SubmissionReceived($form, $submission);

    $envelope = $mailable->envelope();
    expect($envelope->from->address)->toEqual('noreply@activebrand.com');
    expect($envelope->from->name)->toEqual('Active Brand Alerts');

    /** @var Factory&MockInterface $factory */
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('mailer')->once()->with('flowform')->andReturn(Mockery::mock(Mailer::class)->shouldReceive('send')->getMock());

    $mailable->send($factory);
    expect(config('mail.mailers.flowform.host'))->toEqual('smtp.queued-relay.com');
    expect(config('mail.mailers.flowform.port'))->toEqual(2525);
    expect(config('mail.mailers.flowform.username'))->toEqual('queued_user');
});

test('queued SubmissionReceived falls back to .env mailer when no active MailSetting exists', function () {
    MailSetting::query()->delete();

    $form = Form::create([
        'name' => 'Default Form',
        'slug' => 'default-form',
        'type' => 'endpoint',
        'notification_email' => 'admin@company.com',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['message' => 'Hello'],
    ]);

    $mailable = new SubmissionReceived($form, $submission);

    $envelope = $mailable->envelope();
    expect($envelope->from)->toBeNull();

    /** @var Factory&MockInterface $factory */
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('mailer')->once()->with(null)->andReturn(Mockery::mock(Mailer::class)->shouldReceive('send')->getMock());

    $mailable->send($factory);
});

test('dynamic mailer keeps provider credentials on the mailer, not global mail config', function (string $driver, array $attributes, array $expected) {
    config(['mail.mailers.smtp.host' => 'env-smtp-host', 'mail.mailers.smtp.username' => 'env-smtp-user']);

    (new MailSetting(['driver' => $driver, 'is_active' => true] + $attributes))->applyDynamicMailer('flowform');

    foreach ($expected as $key => $value) {
        expect(config("mail.mailers.flowform.{$key}"))->toBe($value);
    }
    expect(config('mail.mailers.smtp.host'))->toBe('env-smtp-host');
    expect(config('mail.mailers.smtp.username'))->toBe('env-smtp-user');
})->with([
    'smtp' => ['smtp', ['host' => 'smtp.resend.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'resend', 'password' => 're_1'], ['transport' => 'smtp', 'host' => 'smtp.resend.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'resend', 'password' => 're_1']],
    'log' => ['log', [], ['transport' => 'log']],
]);

test('dynamic smtp mailer never inherits MAIL_URL', function () {
    (new MailSetting(['driver' => 'smtp', 'host' => 'smtp.db-host.test', 'port' => 2525, 'is_active' => true]))->applyDynamicMailer('flowform');

    expect(config('mail.mailers.flowform'))->not->toHaveKey('url');
    expect(config('mail.mailers.flowform.host'))->toBe('smtp.db-host.test');
});

test('Livewire selects Brevo preset and pre-fills host, port, and encryption', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(MailSettingsPage::class)
        ->call('selectPreset', 'brevo')
        ->assertSet('driver', 'smtp')
        ->assertSet('host', 'smtp-relay.brevo.com')
        ->assertSet('port', 587)
        ->assertSet('encryption', 'tls')
        ->assertSet('preset', 'brevo');
});

test('Livewire selects other presets and pre-fills appropriate values', function (string $preset, array $expected) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $test = Livewire::test(MailSettingsPage::class)
        ->call('selectPreset', $preset);

    foreach ($expected as $property => $value) {
        $test->assertSet($property, $value);
    }
})->with([
    'mailgun' => ['mailgun', ['driver' => 'smtp', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls']],
    'smtp2go' => ['smtp2go', ['driver' => 'smtp', 'host' => 'mail.smtp2go.com', 'port' => 587, 'encryption' => 'tls']],
    'resend' => ['resend', ['driver' => 'smtp', 'host' => 'smtp.resend.com', 'port' => 587, 'encryption' => 'tls']],
    'postmark' => ['postmark', ['driver' => 'smtp', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls']],
    'ses' => ['ses', ['driver' => 'smtp', 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls']],
    'generic' => ['smtp', ['driver' => 'smtp', 'host' => '', 'port' => 587, 'encryption' => 'tls']],
    'log' => ['log', ['driver' => 'log', 'host' => '']],
]);

test('Livewire detectPreset recognises resend, postmarkapp, and amazonaws SMTP hosts', function (string $host, string $expectedPreset) {
    MailSetting::query()->delete();
    MailSetting::create([
        'driver' => 'smtp',
        'host' => $host,
        'port' => 587,
        'encryption' => 'tls',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(MailSettingsPage::class)
        ->assertSet('preset', $expectedPreset);
})->with([
    'resend' => ['smtp.resend.com', 'resend'],
    'postmark' => ['smtp.postmarkapp.com', 'postmark'],
    'ses' => ['email-smtp.eu-west-1.amazonaws.com', 'ses'],
]);

test('saving MailSettingsPage rejects legacy API drivers', function (string $driver) {
    MailSetting::query()->delete();
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(MailSettingsPage::class)
        ->set('driver', $driver)
        ->call('save')
        ->assertHasErrors(['driver']);

    expect(MailSetting::count())->toBe(0);
})->with(['ses', 'resend', 'postmark']);

test('migration deactivates existing ses, resend, and postmark mail_settings rows', function () {
    MailSetting::query()->delete();

    $ses = MailSetting::create(['driver' => 'ses', 'is_active' => true]);
    $resend = MailSetting::create(['driver' => 'resend', 'is_active' => true]);
    $postmark = MailSetting::create(['driver' => 'postmark', 'is_active' => true]);
    $smtp = MailSetting::create(['driver' => 'smtp', 'host' => 'smtp.resend.com', 'is_active' => true]);

    $migration = require database_path('migrations/2026_10_27_110000_deactivate_unsupported_mail_drivers.php');
    $migration->up();

    expect($ses->fresh()->is_active)->toBeFalse()
        ->and($resend->fresh()->is_active)->toBeFalse()
        ->and($postmark->fresh()->is_active)->toBeFalse()
        ->and($smtp->fresh()->is_active)->toBeTrue();
});

test('SubmissionReceived envelope sets replyTo from email', function () {
    $form = Form::create([
        'name' => 'Contact',
        'slug' => 'contact-reply-to',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => [
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ],
    ]);

    $mailable = new SubmissionReceived($form, $submission);
    $envelope = $mailable->envelope();

    expect($envelope->hasReplyTo('bob@example.com'))->toBeTrue();
});

test('SubmissionReceived envelope prefers _replyto when both _replyto and email exist', function () {
    $form = Form::create([
        'name' => 'Contact',
        'slug' => 'contact-reply-to-override',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            '_replyto' => 'override@example.com',
        ],
    ]);

    $mailable = new SubmissionReceived($form, $submission);
    $envelope = $mailable->envelope();

    expect($envelope->hasReplyTo('override@example.com'))->toBeTrue();
    expect($envelope->hasReplyTo('bob@example.com'))->toBeFalse();
});

test('SubmissionReceived envelope has no replyTo when value is invalid', function () {
    $form = Form::create([
        'name' => 'Contact',
        'slug' => 'contact-reply-to-invalid',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => [
            'name' => 'Bob',
            'email' => 'not-an-email',
        ],
    ]);

    $mailable = new SubmissionReceived($form, $submission);
    $envelope = $mailable->envelope();

    expect($envelope->replyTo)->toBeEmpty();
});

test('MailSettingsPage on demo host shows info notification and does not attempt mail send', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    request()->headers->set('HOST', 'demo.flowformhq.com');

    Mail::fake();

    Livewire::test(MailSettingsPage::class)
        ->set('test_recipient', 'test@example.com')
        ->call('sendTestEmail')
        ->assertNotified('Demo Instance')
        ->assertSet('testResult', null);

    Mail::assertNothingSent();
});

test('MailSettingsPage on non-demo host ignores demo query parameter and attempts send', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->withServerVariables(['HTTP_HOST' => 'app.flowformhq.com']);
    request()->merge(['demo' => '1']);

    Mail::fake();

    $component = Livewire::test(MailSettingsPage::class)
        ->set('test_recipient', 'test@example.com')
        ->call('sendTestEmail');

    expect($component->get('testResult'))->not->toBeNull();
    expect($component->get('testResult')['message'])->toContain('test@example.com');
});
