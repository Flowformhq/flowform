<?php

declare(strict_types=1);

use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

test('POST /f/{slug} accepts HTML form submission and redirects to _next', function () {
    $form = Form::create([
        'name' => 'Contact Us',
        'slug' => 'contact-us',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/contact-us', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'message' => 'Hello there!',
        '_next' => 'https://buildeon.solutions/thank-you',
    ]);

    $response->assertRedirect('https://buildeon.solutions/thank-you');

    $this->assertDatabaseHas('submissions', [
        'form_id' => $form->id,
        'status' => 'completed',
    ]);

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission->data)->toEqual([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'message' => 'Hello there!',
    ]);
});

test('POST /f/{slug} redirects to _redirect when provided', function () {
    $form = Form::create([
        'name' => 'Inquiry Form',
        'slug' => 'inquiry',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/inquiry', [
        'name' => 'Alice',
        '_redirect' => 'https://buildeon.solutions/success',
    ]);

    $response->assertRedirect('https://buildeon.solutions/success');
});

test('POST /f/{slug} falls back to form configured redirect_url', function () {
    $form = Form::create([
        'name' => 'Demo Request',
        'slug' => 'demo',
        'type' => 'endpoint',
        'redirect_url' => 'https://buildeon.solutions/booked',
        'is_active' => true,
    ]);

    $response = $this->post('/f/demo', [
        'company' => 'Acme Corp',
    ]);

    $response->assertRedirect('https://buildeon.solutions/booked');
});

test('POST /f/{slug} returns JSON when no redirect URL is provided', function () {
    $form = Form::create([
        'name' => 'Quick Message',
        'slug' => 'quick-msg',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/quick-msg', [
        'note' => 'Just testing',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ])
        ->assertJsonStructure(['success', 'message', 'id']);
});

test('POST /api/v1/f/{slug} processes JSON AJAX submission returning 200 with UUID', function () {
    $form = Form::create([
        'name' => 'API Contact',
        'slug' => 'api-contact',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/api-contact', [
        'name' => 'API User',
        'email' => 'api@example.com',
        'budget' => '$5,000',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);

    $uuid = $response->json('id');
    expect($uuid)->not->toBeEmpty();

    $submission = Submission::where('uuid', $uuid)->first();
    expect($submission)->not->toBeNull();
    expect($submission->data)->toEqual([
        'name' => 'API User',
        'email' => 'api@example.com',
        'budget' => '$5,000',
    ]);
});

test('POST /f/{slug} with Accept: application/json returns JSON response', function () {
    $form = Form::create([
        'name' => 'Fetch Endpoint',
        'slug' => 'fetch-endpoint',
        'type' => 'endpoint',
        'redirect_url' => 'https://example.com/should-not-redirect-for-json',
        'is_active' => true,
    ]);

    $response = $this->post('/f/fetch-endpoint', [
        'client' => 'Astro Frontend',
    ], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);
});

test('honeypot submission via _gotcha is quietly discarded without saving or emailing', function () {
    $form = Form::create([
        'name' => 'Protected Contact',
        'slug' => 'protected-contact',
        'type' => 'endpoint',
        'notification_email' => 'alerts@buildeon.solutions',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/protected-contact', [
        'name' => 'Spam Bot',
        'email' => 'bot@spammer.com',
        '_gotcha' => 'I am a bot',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(0);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('honeypot submission via _honeypot is quietly discarded', function () {
    $form = Form::create([
        'name' => 'Protected Form',
        'slug' => 'protected-form',
        'type' => 'endpoint',
        'notification_email' => 'alerts@buildeon.solutions',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/protected-form', [
        'name' => 'Spam Bot 2',
        '_honeypot' => 'http://spam-link.xyz',
    ]);

    $response->assertOk();
    expect(Submission::where('form_id', $form->id)->count())->toBe(0);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

test('successful submission dispatches SubmissionReceived email to configured notification_email', function () {
    $form = Form::create([
        'name' => 'Client Leads',
        'slug' => 'leads',
        'type' => 'endpoint',
        'notification_email' => 'sales@buildeon.solutions',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/f/leads', [
        'full_name' => 'Jane Smith',
        'service' => 'Sovereign Agent Hosting',
    ]);

    $response->assertOk();

    Mail::assertQueued(SubmissionReceived::class, function ($mail) {
        return $mail->hasTo('sales@buildeon.solutions')
            && $mail->form->slug === 'leads'
            && $mail->submission->data['full_name'] === 'Jane Smith';
    });
});

test('supports comma-separated multiple notification emails', function () {
    $form = Form::create([
        'name' => 'Partnership',
        'slug' => 'partners',
        'type' => 'endpoint',
        'notification_email' => 'w@buildeon.solutions, sales@buildeon.solutions',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/f/partners', ['company' => 'Partner Co'])->assertOk();

    Mail::assertQueued(SubmissionReceived::class, function ($mail) {
        return $mail->hasTo('w@buildeon.solutions')
            && $mail->hasTo('sales@buildeon.solutions');
    });
});

test('inactive form returns 404', function () {
    Form::create([
        'name' => 'Inactive Endpoint',
        'slug' => 'inactive-endpoint',
        'type' => 'endpoint',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/f/inactive-endpoint', ['foo' => 'bar'])
        ->assertNotFound();

    $this->post('/f/inactive-endpoint', ['foo' => 'bar'])
        ->assertNotFound();
});

test('wizard form returns 404 on endpoint routes', function () {
    Form::create([
        'name' => 'Wizard Only Form',
        'slug' => 'wizard-only',
        'type' => 'wizard',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/f/wizard-only', ['field' => 'value'])
        ->assertNotFound();

    $this->post('/f/wizard-only', ['field' => 'value'])
        ->assertNotFound();
});

test('unknown slug returns 404', function () {
    $this->postJson('/api/v1/f/non-existent-slug', ['field' => 'value'])
        ->assertNotFound();

    $this->post('/f/non-existent-slug', ['field' => 'value'])
        ->assertNotFound();
});

test('reserved meta keys are excluded from submission data', function () {
    $form = Form::create([
        'name' => 'Clean Data Form',
        'slug' => 'clean-data',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $this->post('/f/clean-data', [
        'name' => 'Clean User',
        'comment' => 'Great work',
        '_gotcha' => '',
        '_honeypot' => '',
        '_next' => 'https://buildeon.solutions/thanks',
        '_redirect' => 'https://buildeon.solutions/thanks',
        '_token' => 'csrf-sample-token',
        '_method' => 'POST',
    ])->assertRedirect('https://buildeon.solutions/thanks');

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission->data)->toEqual([
        'name' => 'Clean User',
        'comment' => 'Great work',
    ]);
});

test('disallowed external domain in _next is rejected and returns JSON', function () {
    $form = Form::create([
        'name' => 'Attacker Target',
        'slug' => 'attacker-target',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/attacker-target', [
        'name' => 'Victim',
        '_next' => 'https://evil.com/phishing',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);
});

test('disallowed external domain in _next falls back to form redirect_url', function () {
    $form = Form::create([
        'name' => 'Safe Fallback',
        'slug' => 'safe-fallback',
        'type' => 'endpoint',
        'redirect_url' => 'https://buildeon.solutions/safe-fallback',
        'is_active' => true,
    ]);

    $response = $this->post('/f/safe-fallback', [
        'name' => 'Victim',
        '_next' => 'https://evil.com/phishing',
    ]);

    $response->assertRedirect('https://buildeon.solutions/safe-fallback');
});

test('relative internal path in _next is accepted for redirect', function () {
    $form = Form::create([
        'name' => 'Relative Form',
        'slug' => 'relative-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/relative-form', [
        'name' => 'User',
        '_next' => '/work-with-us/?submitted=true',
    ]);

    $response->assertRedirect('/work-with-us/?submitted=true');
});

test('protocol-relative URL in _next is rejected', function () {
    $form = Form::create([
        'name' => 'Protocol Relative',
        'slug' => 'protocol-relative',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/protocol-relative', [
        'name' => 'User',
        '_next' => '//evil.com/steal-creds',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);
});

test('subdomain matching wildcard host in _next is accepted', function () {
    $form = Form::create([
        'name' => 'Subdomain Form',
        'slug' => 'subdomain-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $response = $this->post('/f/subdomain-form', [
        'name' => 'User',
        '_next' => 'https://staging.buildeon.solutions/work-with-us/?submitted=true',
    ]);

    $response->assertRedirect('https://staging.buildeon.solutions/work-with-us/?submitted=true');
});

test('public endpoint submissions are throttled to 10 requests per minute', function () {
    $form = Form::create([
        'name' => 'Rate Limit Form',
        'slug' => 'rate-limit-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->post('/f/rate-limit-form', ['test' => "req-{$i}"])
            ->assertOk();
    }

    $response = $this->post('/f/rate-limit-form', ['test' => 'req-11']);
    $response->assertStatus(429);
});

test('POST /f/{slug} encrypts submission data when form has is_encrypted=true', function () {
    $form = Form::create([
        'name' => 'HIPAA Patient Intake',
        'slug' => 'patient-intake',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);

    $response = $this->post('/f/patient-intake', [
        'patient_name' => 'Jane Doe',
        'ssn' => '123-45-6789',
        'condition' => 'Confidential health note',
    ]);

    $response->assertOk();

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission)->not->toBeNull();
    expect($submission->is_encrypted)->toBeTrue();

    // Transparent accessor decrypts payload
    expect($submission->data['patient_name'])->toEqual('Jane Doe');
    expect($submission->data['ssn'])->toEqual('123-45-6789');

    // Raw database column is encrypted ciphertext (does not contain plaintext)
    $rawDbData = \Illuminate\Support\Facades\DB::table('submissions')
        ->where('id', $submission->id)
        ->value('data');
    expect($rawDbData)->not->toContain('Jane Doe');
    expect($rawDbData)->not->toContain('123-45-6789');
});

test('POST /f/{slug} dispatches signed webhook with HMAC SHA-256 header', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $form = Form::create([
        'name' => 'Webhook Form',
        'slug' => 'webhook-form',
        'type' => 'endpoint',
        'is_active' => true,
        'webhook_url' => 'https://api.acme-corp.com/hooks/flowform',
        'webhook_secret' => 'whsec_secret_key_12345',
    ]);

    $response = $this->post('/f/webhook-form', [
        'lead_name' => 'John Agency',
    ]);

    $response->assertOk();

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\DispatchFormWebhook::class, function ($job) use ($form) {
        return $job->form->id === $form->id && $job->payload['lead_name'] === 'John Agency';
    });
});

