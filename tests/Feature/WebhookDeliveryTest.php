<?php

declare(strict_types=1);

use App\Jobs\DeliverWebhook;
use App\Models\Field;
use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use App\Models\Submission;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Rules\ValidWebhookUrl;
use App\Support\TokenAbility;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['flowform.webhooks.allow_private_targets' => false]);
    ValidWebhookUrl::$dnsResolver = fn (string $host) => match ($host) {
        'hooks.example.com', 'zapier.example.com', 'legacy.example.com' => ['93.184.216.34'],
        default => [],
    };
});

test('subscribe then submit sends one signed POST with subscription_id and records succeeded delivery', function () {
    Http::fake([
        'https://hooks.example.com/*' => Http::response(['accepted' => true], 200),
    ]);

    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Contact Us',
        'slug' => 'contact-subscribe-test',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $token = $owner->createToken('hooks', [TokenAbility::WebhooksManage->value])->plainTextToken;
    $subResp = $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://hooks.example.com/zapier',
        'form_uuid' => $form->uuid,
        'source' => 'zapier',
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $subUuid = (string) $subResp->json('data.uuid');
    $secret = (string) $subResp->json('data.secret');

    $this->post('/f/contact-subscribe-test', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($subUuid, $secret) {
        $body = $request->body();
        $json = json_decode($body, true);
        $sigV1 = $request->header('X-FlowForm-Signature')[0] ?? '';
        $sigV2 = $request->header('X-FlowForm-Signature-256')[0] ?? '';
        $timestamp = $request->header('X-FlowForm-Timestamp')[0] ?? '';
        $deliveryId = $request->header('X-FlowForm-Delivery-Id')[0] ?? '';

        preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $sigV2, $m);

        return $request->url() === 'https://hooks.example.com/zapier'
            && ($json['subscription_id'] ?? null) === $subUuid
            && ($json['event'] ?? null) === 'submission.created'
            && ($json['submission']['data']['email'] ?? null) === 'alice@example.com'
            && $deliveryId !== ''
            && hash_equals(hash_hmac('sha256', $body, $secret), $sigV1)
            && ! empty($m)
            && $m[1] === $timestamp
            && hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $m[2]);
    });

    $delivery = WebhookDelivery::first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('succeeded')
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->attempt)->toBe(1)
        ->and($delivery->event)->toBe('submission.created')
        ->and($delivery->duration_ms)->not->toBeNull()
        ->and($delivery->attempted_at)->not->toBeNull();
});

test('form-scoped subscription skips other forms while null-form subscription fires only for owner forms', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $aliceForm1 = Form::create(['user_id' => $alice->id, 'name' => 'A1', 'slug' => 'alice-1', 'type' => 'endpoint', 'is_active' => true]);
    $aliceForm2 = Form::create(['user_id' => $alice->id, 'name' => 'A2', 'slug' => 'alice-2', 'type' => 'endpoint', 'is_active' => true]);
    $bobForm = Form::create(['user_id' => $bob->id, 'name' => 'B1', 'slug' => 'bob-1', 'type' => 'endpoint', 'is_active' => true]);

    $scoped = WebhookSubscription::create([
        'user_id' => $alice->id,
        'form_id' => $aliceForm1->id,
        'target_url' => 'https://hooks.example.com/scoped-a1',
        'events' => ['submission.created'],
        'source' => 'api',
        'is_active' => true,
    ]);

    $wildcard = WebhookSubscription::create([
        'user_id' => $alice->id,
        'form_id' => null,
        'target_url' => 'https://zapier.example.com/all-alice',
        'events' => ['submission.created'],
        'source' => 'zapier',
        'is_active' => true,
    ]);

    $this->post('/f/alice-1', ['email' => 'one@example.com'])->assertOk();
    $this->post('/f/alice-2', ['email' => 'two@example.com'])->assertOk();
    $this->post('/f/bob-1', ['email' => 'bob@example.com'])->assertOk();

    // alice-1 triggers scoped + wildcard (2), alice-2 triggers wildcard (1), bob-1 triggers neither (0)
    Http::assertSentCount(3);
    expect(WebhookDelivery::where('webhook_subscription_id', $scoped->id)->count())->toBe(1);
    expect(WebhookDelivery::where('webhook_subscription_id', $wildcard->id)->count())->toBe(2);
    expect(WebhookDelivery::where('form_id', $bobForm->id)->count())->toBe(0);
});

test('wizard completion dispatches webhook subscriptions with submitted values', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Wizard', 'slug' => 'wizard-hook', 'type' => 'wizard', 'is_active' => true]);
    $step = Step::create(['form_id' => $form->id, 'step_number' => 1, 'title' => 'Step 1']);
    $ft = FieldType::create(['name' => 'text', 'component' => 'text-input']);
    $field = Field::create([
        'form_id' => $form->id,
        'step_id' => $step->id,
        'field_type_id' => $ft->id,
        'code' => 'company',
        'label' => 'Company',
        'order' => 1,
    ]);

    $sub = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://hooks.example.com/wizard',
        'events' => ['submission.created'],
        'source' => 'api',
        'is_active' => true,
    ]);

    $submission = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'draft',
        'current_step' => 1,
    ]);
    $submission->values()->create(['field_id' => $field->id, 'value' => 'Acme Corp']);

    $token = $owner->createToken('wizard-client', [TokenAbility::SubmissionsWrite->value])->plainTextToken;

    $this->patchJson("/api/v1/submissions/{$submission->uuid}", ['status' => 'completed'], [
        'Authorization' => "Bearer {$token}",
    ])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => ($r->data()['submission']['data']['company'] ?? null) === 'Acme Corp');
    expect(WebhookDelivery::where('webhook_subscription_id', $sub->id)->where('status', 'succeeded')->exists())->toBeTrue();
});

test('encrypted submission webhook dispatch records submission.decrypted audit log', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Encrypted Hook',
        'slug' => 'encrypted-hook',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);

    WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://hooks.example.com/enc',
        'events' => ['submission.created'],
        'source' => 'api',
        'is_active' => true,
    ]);

    $this->post('/f/encrypted-hook', ['ssn' => '123-45-6789'])->assertOk();

    $submission = Submission::where('form_id', $form->id)->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'action' => 'submission.decrypted',
        'auditable_type' => Submission::class,
        'auditable_id' => $submission->id,
    ]);
});

test('410 Gone deletes the subscription and keeps delivery row while 500 records attempts and retries', function () {
    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Retry Form', 'slug' => 'retry-form', 'type' => 'endpoint', 'is_active' => true]);

    $goneSub = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://hooks.example.com/gone',
        'events' => ['submission.created'],
        'source' => 'zapier',
        'is_active' => true,
    ]);

    $flakySub = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://zapier.example.com/flaky',
        'events' => ['submission.created'],
        'source' => 'n8n',
        'is_active' => true,
    ]);

    $submission = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'data' => ['email' => 'retry@example.com'],
    ]);

    Http::fake([
        'https://hooks.example.com/gone' => Http::response('Gone', 410),
        'https://zapier.example.com/flaky' => Http::sequence()
            ->push(str_repeat('X', 3000), 500)
            ->push('recovered', 200),
    ]);

    $goneJob = (new DeliverWebhook($form, $submission, $submission->data, $goneSub))->withFakeQueueInteractions();
    $goneJob->handle();
    $goneJob->assertNotReleased();

    expect(WebhookSubscription::find($goneSub->id))->toBeNull();
    $goneDelivery = WebhookDelivery::where('response_code', 410)->first();
    expect($goneDelivery)->not->toBeNull()
        ->and($goneDelivery->status)->toBe('failed')
        ->and($goneDelivery->webhook_subscription_id)->toBeNull();

    // First attempt -> 500, truncated response_body (<= 2048 bytes), scheduled retry with 10s backoff
    $flakyAttempt1 = (new DeliverWebhook($form, $submission, $submission->data, $flakySub))->withFakeQueueInteractions();
    $flakyAttempt1->handle();
    $flakyAttempt1->assertReleased(10);

    $attempt1Row = WebhookDelivery::where('webhook_subscription_id', $flakySub->id)->where('attempt', 1)->firstOrFail();
    expect($attempt1Row->status)->toBe('failed')
        ->and($attempt1Row->response_code)->toBe(500)
        ->and(strlen((string) $attempt1Row->response_body))->toBeLessThanOrEqual(2048);

    // Retry attempt -> 200, same X-FlowForm-Delivery-Id, failure_count reset
    $flakyAttempt2 = (new DeliverWebhook($form, $submission, $submission->data, $flakySub))->withFakeQueueInteractions();
    $flakyAttempt2->handle();
    $flakyAttempt2->assertNotReleased();

    $attempt2Row = WebhookDelivery::where('webhook_subscription_id', $flakySub->id)->where('attempt', 2)->firstOrFail();
    expect($attempt2Row->status)->toBe('succeeded')
        ->and($flakySub->fresh()->failure_count)->toBe(0);

    $sentRequests = Http::recorded(fn (Request $r) => $r->url() === 'https://zapier.example.com/flaky')->values();
    expect($sentRequests->all())->toHaveCount(2);
    expect($sentRequests[0][0]->header('X-FlowForm-Delivery-Id')[0])
        ->toBe($sentRequests[1][0]->header('X-FlowForm-Delivery-Id')[0]);
});

test('50 consecutive failures disable the subscription and idempotency blocks duplicate succeeded rows', function () {
    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Circuit Form', 'is_active' => true]);
    $sub = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://hooks.example.com/circuit',
        'events' => ['submission.created'],
        'failure_count' => 49,
        'is_active' => true,
    ]);
    $submission = Submission::create(['form_id' => $form->id, 'status' => 'completed', 'data' => ['ok' => true]]);

    Http::fake([
        'https://hooks.example.com/circuit' => Http::response('Server Error', 500),
        'https://zapier.example.com/once' => Http::response('ok', 200),
    ]);

    (new DeliverWebhook($form, $submission, $submission->data, $sub))->handle();

    $fresh = $sub->fresh();
    expect($fresh->failure_count)->toBe(50)
        ->and($fresh->disabled_at)->not->toBeNull()
        ->and($fresh->is_active)->toBeFalse();

    // Idempotency: once a submission+subscription succeeds, duplicate job execution sends nothing
    $activeSub = WebhookSubscription::create([
        'user_id' => $owner->id,
        'form_id' => $form->id,
        'target_url' => 'https://zapier.example.com/once',
        'events' => ['submission.created'],
        'is_active' => true,
    ]);

    (new DeliverWebhook($form, $submission, $submission->data, $activeSub))->handle();
    (new DeliverWebhook($form, $submission, $submission->data, $activeSub))->handle();

    expect(Http::recorded(fn (Request $r) => $r->url() === 'https://zapier.example.com/once')->count())->toBe(1);
    expect(WebhookDelivery::where('webhook_subscription_id', $activeSub->id)->where('status', 'succeeded')->count())->toBe(1);
});

test('unsubscribing stops further webhook posts and legacy webhook_url is logged', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Legacy And Hook',
        'slug' => 'legacy-and-hook',
        'type' => 'endpoint',
        'webhook_url' => 'https://legacy.example.com/hook',
        'webhook_secret' => 'whsec_legacy_123',
        'is_active' => true,
    ]);

    $token = $owner->createToken('hooks', [TokenAbility::WebhooksManage->value])->plainTextToken;
    $sub = $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://hooks.example.com/sub',
        'form_uuid' => $form->uuid,
    ], ['Authorization' => "Bearer {$token}"])->assertCreated();

    $subUuid = (string) $sub->json('data.uuid');
    $this->json('DELETE', "/api/v1/webhooks/{$subUuid}", [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

    $this->post('/f/legacy-and-hook', ['name' => 'Legacy Caller'])->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://legacy.example.com/hook');

    $legacyDelivery = WebhookDelivery::whereNull('webhook_subscription_id')->where('form_id', $form->id)->first();
    expect($legacyDelivery)->not->toBeNull()
        ->and($legacyDelivery->status)->toBe('succeeded')
        ->and($legacyDelivery->response_code)->toBe(200);
});
