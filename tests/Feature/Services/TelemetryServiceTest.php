<?php

use App\Models\Form;
use App\Models\Submission;
use App\Models\TelemetryInstall;
use App\Models\User;
use App\Services\Telemetry\NullTelemetryService;
use App\Services\Telemetry\TelemetryContract;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->install = TelemetryInstall::getOrCreate();
});

test('telemetry service is disabled when config is false', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        false,
    );

    expect($service->isEnabled())->toBeFalse();
    expect($service->isOptedIn())->toBeFalse();
});

test('telemetry service is not opted in by default', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    expect($service->isEnabled())->toBeTrue();
    expect($service->isOptedIn())->toBeFalse();
});

test('optIn sets opted_in to true', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();

    expect($this->install->fresh()->opted_in)->toBeTrue();
    expect($service->isOptedIn())->toBeTrue();
});

test('optOut sets opted_in to false', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $service->optOut();

    expect($this->install->fresh()->opted_in)->toBeFalse();
});

test('ping returns skipped when not opted in', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $result = $service->ping();

    expect($result->sent)->toBeFalse();
});

test('ping returns skipped when disabled', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        false,
    );

    $service->optIn();
    $result = $service->ping();

    expect($result->sent)->toBeFalse();
});

test('ping sends payload and returns sent on success', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $result = $service->ping();

    expect($result->sent)->toBeTrue();
    expect($this->install->fresh()->last_ping_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return isset($payload['install_id'])
            && isset($payload['version'])
            && isset($payload['php_version'])
            && isset($payload['database_driver'])
            && isset($payload['response_volume_bucket'])
            && ! isset($payload['user_email'])
            && ! isset($payload['form_content']);
    });
});

test('ping payload contains no PII fields', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $service->ping();

    Http::assertSent(function ($request) {
        $payload = $request->data();
        $forbiddenKeys = ['email', 'name', 'user_id', 'ip', 'password', 'token', 'form_content', 'submission_data'];

        foreach ($forbiddenKeys as $key) {
            if (array_key_exists($key, $payload)) {
                return false;
            }
        }

        return true;
    });
});

test('ping returns failed on server error', function () {
    Http::fake(['*' => Http::response('error', 500)]);

    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $result = $service->ping();

    expect($result->sent)->toBeFalse();
    expect($result->statusCode)->toBe(500);
});

test('ping returns failed on connection error without throwing', function () {
    Http::fake(fn () => throw new Exception('Connection refused'));

    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $result = $service->ping();

    expect($result->sent)->toBeFalse();
    expect($result->statusCode)->toBe(0);
});

test('telemetry install auto-generates uuid', function () {
    $install = TelemetryInstall::getOrCreate();

    expect($install->install_id)->not->toBeNull();
    expect(strlen($install->install_id))->toBe(36);
});

test('response volume bucket returns correct ranges', function () {
    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $reflector = new ReflectionClass($service);
    $method = $reflector->getMethod('getResponseVolumeBucket');
    $method->setAccessible(true);

    expect($method->invoke($service))->toBe('0');
});

test('fresh install config and .env.example default telemetry to disabled and make no HTTP calls', function () {
    Http::fake();

    expect(config('flowform.telemetry.enabled'))->toBeFalse();

    $envExample = (string) file_get_contents(base_path('.env.example'));
    expect($envExample)->toContain('FLOWFORM_TELEMETRY_ENABLED=false');

    $exit = Artisan::call('flowform:telemetry:ping');
    expect($exit)->toBe(0);

    Http::assertNothingSent();
});

test('flowform:telemetry opt-in defaults prompt to no and allows explicit opt-in', function () {
    $this->artisan('flowform:telemetry', ['action' => 'opt-in'])
        ->expectsConfirmation('Do you want to enable anonymous telemetry?', 'no')
        ->assertSuccessful();

    expect($this->install->fresh()->opted_in)->toBeFalse();

    $this->artisan('flowform:telemetry', ['action' => 'opt-in'])
        ->expectsConfirmation('Do you want to enable anonymous telemetry?', 'yes')
        ->assertSuccessful();

    expect($this->install->fresh()->opted_in)->toBeTrue();
});

test('telemetry payload strictly contains only allowlisted fields with no PII, hostnames, emails, IPs, or exact counts', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    config(['app.url' => 'https://secret-app.example.com']);
    $user = User::factory()->create(['email' => 'owner-secret@example.com']);
    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Confidential Onboarding Form',
        'allowed_domains' => 'client-domain.example.org',
        'type' => 'endpoint',
        'is_active' => true,
    ]);
    Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['email' => 'respondent-pii@example.com'],
        'meta' => ['ip' => '203.0.113.77'],
    ]);

    $service = new TelemetryService(
        app(HttpFactory::class),
        'https://example.com/ping',
        true,
    );

    $service->optIn();
    $result = $service->ping();
    expect($result->sent)->toBeTrue();

    Http::assertSent(function ($request) {
        $payload = $request->data();
        $keys = array_keys($payload);
        $allowed = TelemetryService::ALLOWED_PAYLOAD_FIELDS;

        sort($keys);
        sort($allowed);

        $serialized = (string) json_encode($payload);

        return $keys === $allowed
            && $payload['response_volume_bucket'] === '1-99'
            && ! str_contains($serialized, 'owner-secret@example.com')
            && ! str_contains($serialized, 'Confidential Onboarding Form')
            && ! str_contains($serialized, 'client-domain.example.org')
            && ! str_contains($serialized, 'respondent-pii@example.com')
            && ! str_contains($serialized, '203.0.113.77')
            && ! str_contains($serialized, 'secret-app.example.com');
    });
});

test('telemetry interface can be backed by NullTelemetryService in tests', function () {
    Http::fake();

    $nullSink = new NullTelemetryService;
    app()->instance(TelemetryContract::class, $nullSink);

    $resolved = app(TelemetryContract::class);
    expect($resolved)->toBeInstanceOf(NullTelemetryService::class)
        ->and($resolved->isEnabled())->toBeFalse()
        ->and($resolved->isOptedIn())->toBeFalse()
        ->and($resolved->ping()->sent)->toBeFalse();

    $resolved->optIn();
    expect($resolved->isOptedIn())->toBeFalse();

    Artisan::call('flowform:telemetry:ping');
    Http::assertNothingSent();
});
