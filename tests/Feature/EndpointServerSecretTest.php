<?php

declare(strict_types=1);

use App\Filament\Resources\Forms\Pages\EditForm;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

test('captcha-enabled form accepts valid header secret without captcha token and skips captcha HTTP call', function () {
    Http::fake();

    $form = Form::create([
        'name' => 'WP Elementor Form',
        'slug' => 'wp-elementor-form',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_site_key' => '1x00000000000000000000AA',
        'captcha_secret' => '1x0000000000000000000000000000000AA',
        'is_active' => true,
    ]);

    $secret = $form->generateEndpointSecret();

    $response = $this->postJson('/f/wp-elementor-form', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ], [
        'X-FlowForm-Secret' => $secret,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);

    Http::assertNothingSent();

    $submission = Submission::where('form_id', $form->id)->firstOrFail();
    expect($submission->data)->toBe([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
});

test('valid secret via _secret query param works and _secret is absent from data and meta', function () {
    Http::fake();

    $form = Form::create([
        'name' => 'Query Secret Form',
        'slug' => 'query-secret-form',
        'type' => 'endpoint',
        'captcha_provider' => 'hcaptcha',
        'captcha_site_key' => '10000000-ffff-ffff-ffff-000000000001',
        'captcha_secret' => '0x0000000000000000000000000000000000000000',
        'is_active' => true,
    ]);

    $secret = $form->generateEndpointSecret();

    $response = $this->postJson("/f/query-secret-form?_secret={$secret}", [
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    Http::assertNothingSent();

    $submission = Submission::where('form_id', $form->id)->firstOrFail();
    expect($submission->data)->not->toHaveKey('_secret')
        ->and($submission->meta)->not->toHaveKey('_secret')
        ->and($submission->meta)->not->toHaveKey('secret');
});

test('wrong secret returns 403, stores nothing, and does not fall back to captcha', function () {
    Http::fake();

    $form = Form::create([
        'name' => 'Strict Secret Form',
        'slug' => 'strict-secret-form',
        'type' => 'endpoint',
        'captcha_provider' => 'turnstile',
        'captcha_site_key' => '1x0000',
        'captcha_secret' => 'secret_cap',
        'is_active' => true,
    ]);

    $form->generateEndpointSecret();

    $headerResponse = $this->postJson('/f/strict-secret-form', [
        'name' => 'Intruder',
        'cf-turnstile-response' => 'valid-looking-token',
    ], [
        'X-FlowForm-Secret' => 'wrong-secret',
    ]);

    $headerResponse->assertForbidden()
        ->assertJson([
            'success' => false,
            'error' => 'Invalid endpoint secret.',
        ]);

    $queryResponse = $this->postJson('/f/strict-secret-form?_secret=wrong-secret', [
        'name' => 'Intruder',
    ]);

    $queryResponse->assertForbidden()
        ->assertJson([
            'success' => false,
            'error' => 'Invalid endpoint secret.',
        ]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(0);
    Http::assertNothingSent();
});

test('endpoint_secret_required rejects missing secret with 403 and accepts valid secret', function () {
    $form = Form::create([
        'name' => 'Required Secret Form',
        'slug' => 'required-secret-form',
        'type' => 'endpoint',
        'endpoint_secret_required' => true,
        'is_active' => true,
    ]);

    $secret = $form->generateEndpointSecret();

    $this->postJson('/f/required-secret-form', [
        'name' => 'No Secret',
    ])->assertForbidden()
        ->assertJson(['success' => false]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(0);

    $this->postJson('/f/required-secret-form', [
        'name' => 'With Secret',
    ], [
        'X-FlowForm-Secret' => $secret,
    ])->assertOk()
        ->assertJson(['success' => true]);

    expect(Submission::where('form_id', $form->id)->count())->toBe(1);
});

test('valid secret bypasses allowed_domains check even with foreign Origin header', function () {
    $form = Form::create([
        'name' => 'Origin Protected Form',
        'slug' => 'origin-protected-form',
        'type' => 'endpoint',
        'allowed_domains' => 'trusted.example.com',
        'is_active' => true,
    ]);

    $secret = $form->generateEndpointSecret();

    $this->postJson('/f/origin-protected-form', [
        'name' => 'Blocked Browser',
    ], [
        'Origin' => 'https://foreign.example.org',
    ])->assertForbidden();

    $this->postJson('/f/origin-protected-form', [
        'name' => 'Trusted Server Proxy',
    ], [
        'Origin' => 'https://foreign.example.org',
        'X-FlowForm-Secret' => $secret,
    ])->assertOk()
        ->assertJson(['success' => true]);
});

test('valid _source is stored in meta.source and absent from data while invalid _source values are ignored', function () {
    $form = Form::create([
        'name' => 'Source Tracking Form',
        'slug' => 'source-tracking-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $this->postJson('/f/source-tracking-form?_source=wp-elementor', [
        'name' => 'Site Visitor',
    ])->assertOk();

    $first = Submission::where('form_id', $form->id)->latest('id')->firstOrFail();
    expect($first->meta['source'] ?? null)->toBe('wp-elementor')
        ->and($first->data)->not->toHaveKey('_source');

    $this->postJson('/f/source-tracking-form', [
        'name' => 'Body Source Visitor',
        '_source' => 'wp-bricks-v2',
    ])->assertOk();

    $second = Submission::where('form_id', $form->id)->latest('id')->firstOrFail();
    expect($second->meta['source'] ?? null)->toBe('wp-bricks-v2')
        ->and($second->data)->not->toHaveKey('_source');

    foreach (['<script>alert(1)</script>', 'UPPERCASE', str_repeat('a', 65), 'bad space'] as $invalid) {
        $this->postJson('/f/source-tracking-form?_source='.urlencode($invalid), [
            'name' => 'Invalid Source',
        ])->assertOk();

        $latest = Submission::where('form_id', $form->id)->latest('id')->firstOrFail();
        expect($latest->meta)->not->toHaveKey('source')
            ->and($latest->data)->not->toHaveKey('_source');
    }
});

test('only sha256 hash is stored in database and hidden from API schema responses', function () {
    $form = Form::create([
        'name' => 'Hashed Secret Form',
        'slug' => 'hashed-secret-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $plaintext = $form->generateEndpointSecret();

    expect(strlen($plaintext))->toBe(40);

    $rawRow = DB::table('forms')->where('id', $form->id)->first();
    $serializedRow = json_encode($rawRow);

    expect($rawRow->endpoint_secret_hash)->toBe(hash('sha256', $plaintext))
        ->and($serializedRow)->not->toContain($plaintext);

    $schemaJson = $this->getJson("/api/v1/forms/{$form->uuid}/schema")->assertOk()->getContent();
    expect($schemaJson)->not->toContain('endpoint_secret_hash')
        ->and($schemaJson)->not->toContain($rawRow->endpoint_secret_hash)
        ->and($schemaJson)->not->toContain($plaintext);
});

test('Filament EditForm generates secret once, rotates hash, removes secret, and toggles requirement', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Builder Webhook Form',
        'slug' => 'builder-webhook-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $this->actingAs($owner);

    $livewire = Livewire::test(EditForm::class, ['record' => $form->getKey()])
        ->callAction('generate_server_secret')
        ->assertActionMounted('revealServerSecret')
        ->assertMountedActionModalSee('Copy this secret now')
        ->assertMountedActionModalSee('X-FlowForm-Secret');

    $form->refresh();
    $firstHash = $form->endpoint_secret_hash;
    $firstRevealed = $livewire->get('mountedActions.0.arguments.secret');

    expect($firstHash)->not->toBeNull()
        ->and(strlen((string) $firstHash))->toBe(64)
        ->and(strlen((string) $firstRevealed))->toBe(40)
        ->and(hash('sha256', (string) $firstRevealed))->toBe($firstHash);

    $livewire->assertMountedActionModalSee((string) $firstRevealed);

    // Fresh page load must never show plaintext again
    Livewire::test(EditForm::class, ['record' => $form->getKey()])
        ->assertActionNotMounted('revealServerSecret')
        ->assertDontSee((string) $firstRevealed);

    // Rotating changes the hash and invalidates the old secret
    $rotatedLivewire = Livewire::test(EditForm::class, ['record' => $form->getKey()])
        ->callAction('generate_server_secret')
        ->assertActionMounted('revealServerSecret');

    $form->refresh();
    $rotatedRevealed = $rotatedLivewire->get('mountedActions.0.arguments.secret');

    expect($form->endpoint_secret_hash)->not->toBeNull()
        ->and($form->endpoint_secret_hash)->not->toBe($firstHash)
        ->and($form->verifyEndpointSecret((string) $firstRevealed))->toBeFalse()
        ->and($form->verifyEndpointSecret((string) $rotatedRevealed))->toBeTrue();

    // Toggle endpoint_secret_required and save
    Livewire::test(EditForm::class, ['record' => $form->getKey()])
        ->fillForm(['endpoint_secret_required' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($form->fresh()->endpoint_secret_required)->toBeTrue();

    // Remove server secret clears hash
    Livewire::test(EditForm::class, ['record' => $form->getKey()])
        ->callAction('remove_server_secret');

    expect($form->fresh()->endpoint_secret_hash)->toBeNull();
});
