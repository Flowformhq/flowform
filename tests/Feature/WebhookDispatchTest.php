<?php

declare(strict_types=1);

use App\Jobs\DispatchFormWebhook;
use App\Models\Form;
use App\Models\Submission;
use App\Rules\ValidWebhookUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    config(['flowform.webhooks.allow_private_targets' => false]);
    ValidWebhookUrl::$dnsResolver = null;
});

test('outbound webhook rejects loopback, private RFC1918, link-local, and ipv6 targets when allow_private_targets is false', function (string $privateUrl) {
    Http::fake();

    $form = Form::create([
        'name' => 'Internal Test Form',
        'slug' => 'internal-test',
        'type' => 'endpoint',
        'webhook_url' => $privateUrl,
        'webhook_secret' => 'whsec_secret_123',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['lead' => 'Test Lead'],
    ]);

    (new DispatchFormWebhook($form, $submission, ['lead' => 'Test Lead']))->handle();

    Http::assertNothingSent();
})->with([
    'http://127.0.0.1/webhook',
    'http://localhost/webhook',
    'http://10.0.0.5:8000/api/hook',
    'http://169.254.169.254/latest/meta-data',
    'http://[::1]/webhook',
    'http://100.64.0.1/hook',
]);

test('outbound webhook permits private targets when allow_private_targets is true', function () {
    config(['flowform.webhooks.allow_private_targets' => true]);

    Http::fake([
        'http://127.0.0.1:9000/webhook' => Http::response(['received' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Internal Allowed Form',
        'slug' => 'internal-allowed',
        'type' => 'endpoint',
        'webhook_url' => 'http://127.0.0.1:9000/webhook',
        'webhook_secret' => 'whsec_secret_123',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['lead' => 'Internal Lead'],
    ]);

    (new DispatchFormWebhook($form, $submission, ['lead' => 'Internal Lead']))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:9000/webhook';
    });
});

test('outbound webhook does not follow redirects', function () {
    ValidWebhookUrl::$dnsResolver = fn ($host) => ['93.184.216.34'];

    Http::fake([
        'https://public.example.com/webhook' => Http::response('', 302, ['Location' => 'http://127.0.0.1/exploit']),
    ]);

    $form = Form::create([
        'name' => 'Redirect Form',
        'slug' => 'redirect-form',
        'type' => 'endpoint',
        'webhook_url' => 'https://public.example.com/webhook',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['lead' => 'Redirect Test'],
    ]);

    (new DispatchFormWebhook($form, $submission, ['lead' => 'Redirect Test']))->handle();

    // Exactly one request to the initial public endpoint, none to the redirect location
    Http::assertSentCount(1);
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '127.0.0.1');
    });
});

test('outbound webhook includes both legacy signature and timestamp-signed v2 signature', function () {
    ValidWebhookUrl::$dnsResolver = fn ($host) => ['93.184.216.34'];
    $recordedHeaders = [];
    $recordedBody = null;

    Http::fake([
        'https://api.external-crm.com/hooks' => function ($request) use (&$recordedHeaders, &$recordedBody) {
            $recordedHeaders = $request->headers();
            $recordedBody = $request->body();

            return Http::response(['success' => true], 200);
        },
    ]);

    $form = Form::create([
        'name' => 'Signed Hook Form',
        'slug' => 'signed-hook-form',
        'type' => 'endpoint',
        'webhook_url' => 'https://api.external-crm.com/hooks',
        'webhook_secret' => 'whsec_test_secret_xyz',
        'is_active' => true,
    ]);

    $submission = new Submission([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['company' => 'Acme Inc'],
    ]);

    (new DispatchFormWebhook($form, $submission, ['company' => 'Acme Inc']))->handle();

    expect($recordedHeaders)->toHaveKey('X-FlowForm-Signature');
    expect($recordedHeaders)->toHaveKey('X-FlowForm-Signature-256');
    expect($recordedHeaders)->toHaveKey('X-FlowForm-Timestamp');

    $timestamp = $recordedHeaders['X-FlowForm-Timestamp'][0];
    $sigV2 = $recordedHeaders['X-FlowForm-Signature-256'][0];

    // Parse v2 signature format: t=<timestamp>,v1=<hex>
    expect($sigV2)->toMatch('/^t=(\d+),v1=([a-f0-9]{64})$/');
    preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $sigV2, $matches);
    expect($matches[1])->toEqual($timestamp);

    // Verify v2 signature hash: hash_hmac('sha256', "<timestamp>.<body>", secret)
    $expectedV2 = hash_hmac('sha256', "{$timestamp}.{$recordedBody}", 'whsec_test_secret_xyz');
    expect($matches[2])->toEqual($expectedV2);

    // Verify legacy v1 signature hash: hash_hmac('sha256', <body>, secret)
    $expectedV1 = hash_hmac('sha256', (string) $recordedBody, 'whsec_test_secret_xyz');
    expect($recordedHeaders['X-FlowForm-Signature'][0])->toEqual($expectedV1);
});

test('ValidWebhookUrl validator rejects internal IPs and accepts public domains', function () {
    ValidWebhookUrl::$dnsResolver = fn ($host) => match ($host) {
        'internal.local' => ['10.0.0.1'],
        'api.external-crm.com' => ['93.184.216.34'],
        default => [],
    };

    $validatorPrivate = Validator::make(
        ['url' => 'http://127.0.0.1/hook'],
        ['url' => [new ValidWebhookUrl]]
    );
    expect($validatorPrivate->fails())->toBeTrue();

    $validatorCgnat = Validator::make(
        ['url' => 'http://100.64.0.1/hook'],
        ['url' => [new ValidWebhookUrl]]
    );
    expect($validatorCgnat->fails())->toBeTrue();

    $validatorInternal = Validator::make(
        ['url' => 'http://internal.local/hook'],
        ['url' => [new ValidWebhookUrl]]
    );
    expect($validatorInternal->fails())->toBeTrue();

    $validatorPublic = Validator::make(
        ['url' => 'https://api.external-crm.com/hook'],
        ['url' => [new ValidWebhookUrl]]
    );
    expect($validatorPublic->passes())->toBeTrue();
});
