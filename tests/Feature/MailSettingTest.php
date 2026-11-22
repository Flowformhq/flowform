<?php

declare(strict_types=1);

use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\MailSetting;
use App\Models\Submission;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

    $capturedMailerName = null;
    $fakeFactory = new class($capturedMailerName) implements Factory
    {
        public function __construct(public &$captured) {}

        public function mailer($name = null)
        {
            $this->captured = $name;
            $fakeMailer = Mockery::mock(Mailer::class);
            $fakeMailer->shouldReceive('send')->andReturnNull();

            return $fakeMailer;
        }
    };

    $mailable->send($fakeFactory);

    expect($capturedMailerName)->toEqual('flowform');
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

    $capturedMailerName = 'initial';
    $fakeFactory = new class($capturedMailerName) implements Factory
    {
        public function __construct(public &$captured) {}

        public function mailer($name = null)
        {
            $this->captured = $name;
            $fakeMailer = Mockery::mock(Mailer::class);
            $fakeMailer->shouldReceive('send')->andReturnNull();

            return $fakeMailer;
        }
    };

    $mailable->send($fakeFactory);

    expect($capturedMailerName)->toBeNull();
});

test('dynamic mailer keeps provider credentials on the mailer, not global services config', function (string $driver, array $attributes, array $expected) {
    config(['services.ses.key' => 'env-ses-key', 'services.postmark.token' => 'env-pm', 'services.resend.key' => 'env-resend']);

    (new MailSetting(['driver' => $driver, 'is_active' => true] + $attributes))->applyDynamicMailer('flowform');

    foreach ($expected as $key => $value) {
        expect(config("mail.mailers.flowform.{$key}"))->toBe($value);
    }
    expect(config('services.ses.key'))->toBe('env-ses-key');
    expect(config('services.postmark.token'))->toBe('env-pm');
    expect(config('services.resend.key'))->toBe('env-resend');
})->with([
    'ses' => ['ses', ['username' => 'AKIA1', 'password' => 'sec', 'region' => 'eu-west-1'], ['transport' => 'ses', 'key' => 'AKIA1', 'secret' => 'sec', 'region' => 'eu-west-1']],
    'resend' => ['resend', ['api_key' => 're_1'], ['transport' => 'resend', 'key' => 're_1']],
    'postmark' => ['postmark', ['api_key' => 'pm_1'], ['transport' => 'postmark', 'token' => 'pm_1']],
]);

test('dynamic smtp mailer never inherits MAIL_URL', function () {
    (new MailSetting(['driver' => 'smtp', 'host' => 'smtp.db-host.test', 'port' => 2525, 'is_active' => true]))->applyDynamicMailer('flowform');

    expect(config('mail.mailers.flowform'))->not->toHaveKey('url');
    expect(config('mail.mailers.flowform.host'))->toBe('smtp.db-host.test');
});
