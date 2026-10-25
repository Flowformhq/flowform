<?php

declare(strict_types=1);

use App\Mail\SubmissionReceived;
use App\Models\Form;
use App\Models\Submission;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
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
        '_next' => 'https://example.com/thanks',
        '_redirect' => 'https://example.com/thanks',
        '_token' => 'csrf-sample-token',
        '_method' => 'POST',
    ])->assertRedirect('https://example.com/thanks');

    $submission = Submission::where('form_id', $form->id)->first();
    expect($submission->data)->toEqual([
        'name' => 'Clean User',
        'comment' => 'Great work',
    ]);
});
