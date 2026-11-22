<?php

declare(strict_types=1);

use App\Models\MailSetting;
use Illuminate\Support\Facades\Crypt;
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
    $raw = \Illuminate\Support\Facades\DB::table('mail_settings')->where('id', $setting->id)->first();
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
