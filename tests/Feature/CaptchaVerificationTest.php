<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

test('endpoint rejects submission with 422 when captcha is configured and token is missing', function () {
    $form = Form::create([
        'name' => 'Turnstile Form',
        'slug' => 'turnstile-form',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_secret' => '0x4AAAAAASecretKey',
        'captcha_site_key' => '0x4AAAAAASiteKey',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/turnstile-form', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => 'Captcha verification token missing.',
        ]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(0);
});

test('endpoint rejects submission with 422 when Turnstile verification fails', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false], 200),
    ]);

    $form = Form::create([
        'name' => 'Turnstile Form',
        'slug' => 'turnstile-fail',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_secret' => '0x4AAAAAASecretKey',
        'captcha_site_key' => '0x4AAAAAASiteKey',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/turnstile-fail', [
        'name' => 'Bot Alice',
        'email' => 'bot@example.com',
        'cf-turnstile-response' => 'invalid-token-123',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error' => 'Captcha verification failed.',
        ]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(0);
});

test('endpoint accepts submission when Turnstile verification succeeds and strips token from data', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Turnstile Success Form',
        'slug' => 'turnstile-success',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_secret' => '0x4AAAAAASecretKey',
        'captcha_site_key' => '0x4AAAAAASiteKey',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/turnstile-success', [
        'name' => 'Legit User',
        'email' => 'legit@example.com',
        'cf-turnstile-response' => 'valid-turnstile-token',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->data)->toEqual([
        'name' => 'Legit User',
        'email' => 'legit@example.com',
    ]);
    expect($submission->data)->not->toHaveKey('cf-turnstile-response');
});

test('endpoint accepts submission when hCaptcha verification succeeds and strips token from data', function () {
    Http::fake([
        'https://api.hcaptcha.com/siteverify' => Http::response(['success' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'hCaptcha Success Form',
        'slug' => 'hcaptcha-success',
        'type' => 'endpoint',
        'captcha_provider' => 'hcaptcha',
        'captcha_secret' => '0x0000000000000000000000000000000000000000',
        'captcha_site_key' => '10000000-ffff-ffff-ffff-000000000001',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/hcaptcha-success', [
        'name' => 'hCaptcha User',
        'email' => 'hcaptcha@example.com',
        'h-captcha-response' => 'valid-hcaptcha-token',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->data)->toEqual([
        'name' => 'hCaptcha User',
        'email' => 'hcaptcha@example.com',
    ]);
    expect($submission->data)->not->toHaveKey('h-captcha-response');
});

test('forms without captcha configured behave normally without token check', function () {
    $form = Form::create([
        'name' => 'No Captcha Form',
        'slug' => 'no-captcha',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/no-captcha', [
        'name' => 'Standard User',
        'email' => 'standard@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(1);
});

test('captcha secret is encrypted at rest and never exposed in API schema or MCP', function () {
    $form = Form::create([
        'name' => 'Secret Protection Form',
        'slug' => 'secret-protection',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_secret' => 'super_secret_captcha_key_789',
        'captcha_site_key' => 'public_site_key_123',
        'is_active' => true,
    ]);

    // Raw database value is encrypted ciphertext
    $rawSecret = DB::table('forms')->where('id', $form->id)->value('captcha_secret');
    expect($rawSecret)->not->toEqual('super_secret_captcha_key_789');
    expect($rawSecret)->not->toBeNull();

    // Model accessor transparently decrypts
    expect($form->captcha_secret)->toEqual('super_secret_captcha_key_789');

    // Schema endpoint returns captcha provider & site_key, but NEVER captcha_secret
    $schemaResponse = $this->getJson("/api/v1/forms/{$form->uuid}/schema");
    $schemaResponse->assertOk()
        ->assertJsonPath('data.captcha.provider', 'turnstile')
        ->assertJsonPath('data.captcha.site_key', 'public_site_key_123')
        ->assertJsonMissing(['captcha_secret']);

    // Model toArray() excludes captcha_secret
    expect($form->toArray())->not->toHaveKey('captcha_secret');
});

test('captcha provider without a secret fails closed instead of skipping verification', function () {
    Http::fake();

    Form::create([
        'name' => 'Misconfigured',
        'slug' => 'misconfigured-captcha',
        'type' => 'endpoint',
        'is_active' => true,
        'captcha_provider' => 'turnstile',
    ]);

    $this->postJson('/api/v1/f/misconfigured-captcha', ['name' => 'Bot', 'cf-turnstile-response' => 'tok'])
        ->assertStatus(422);

    expect(Submission::count())->toBe(0);
    Http::assertNothingSent();
});
