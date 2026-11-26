<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Rules\ValidWebhookUrl;
use App\Support\TokenAbility;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['flowform.webhooks.allow_private_targets' => false]);
    ValidWebhookUrl::$dnsResolver = fn (string $host) => match ($host) {
        'hooks.example.com', 'zapier.example.com' => ['93.184.216.34'],
        'internal.local' => ['10.0.0.5'],
        default => [],
    };
});

test('POST /api/v1/webhooks creates form-scoped subscription returning secret only on creation', function () {
    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Lead Capture', 'type' => 'endpoint', 'is_active' => true]);
    $token = $owner->createToken('zapier', [TokenAbility::WebhooksManage->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $createResponse = $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://hooks.example.com/flowform',
        'form_uuid' => $form->uuid,
        'source' => 'zapier',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.target_url', 'https://hooks.example.com/flowform')
        ->assertJsonPath('data.form_uuid', $form->uuid)
        ->assertJsonPath('data.events', ['submission.created'])
        ->assertJsonPath('data.source', 'zapier')
        ->assertJsonPath('data.is_active', true);

    $secret = (string) $createResponse->json('data.secret');
    $uuid = (string) $createResponse->json('data.uuid');
    expect($createResponse->json('data.id'))->toBe($uuid);
    expect($secret)->toStartWith('whsec_');

    // Secret must be encrypted at rest in DB
    $rawSecret = DB::table('webhook_subscriptions')->where('uuid', $uuid)->value('secret');
    expect($rawSecret)->not->toBe($secret);

    // GET /api/v1/webhooks never returns secret
    $listResponse = $this->getJson('/api/v1/webhooks', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $uuid)
        ->assertJsonMissingPath('data.0.secret');

    expect(json_encode($listResponse->json()))->not->toContain($secret);
});

test('POST /api/v1/webhooks allows null form_uuid and isolates listing & delete by owner', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $bobForm = Form::create(['user_id' => $bob->id, 'name' => 'Bob Form', 'is_active' => true]);

    $aliceToken = $alice->createToken('n8n', [TokenAbility::WebhooksManage->value])->plainTextToken;
    $bobToken = $bob->createToken('make', [TokenAbility::WebhooksManage->value])->plainTextToken;

    $created = $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://zapier.example.com/hook',
        'source' => 'n8n',
    ], ['Authorization' => "Bearer {$aliceToken}"])
        ->assertCreated()
        ->assertJsonPath('data.form_uuid', null);

    $subUuid = (string) $created->json('data.uuid');

    // Alice cannot subscribe to Bob's form (404 to avoid leaking existence)
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://hooks.example.com/steal',
        'form_uuid' => $bobForm->uuid,
    ], ['Authorization' => "Bearer {$aliceToken}"])
        ->assertNotFound();

    // Bob does not see Alice's subscription and gets 404 deleting it
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/webhooks', ['Authorization' => "Bearer {$bobToken}"])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->json('DELETE', "/api/v1/webhooks/{$subUuid}", [], ['Authorization' => "Bearer {$bobToken}"])
        ->assertNotFound();

    // Alice deletes her own subscription -> 204
    app('auth')->forgetGuards();
    $this->json('DELETE', "/api/v1/webhooks/{$subUuid}", [], ['Authorization' => "Bearer {$aliceToken}"])
        ->assertNoContent();

    $this->getJson('/api/v1/webhooks', ['Authorization' => "Bearer {$aliceToken}"])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('POST /api/v1/webhooks rejects private target URLs and unsupported events', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api', [TokenAbility::WebhooksManage->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson('/api/v1/webhooks', [
        'target_url' => 'http://127.0.0.1:8080/internal',
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['target_url']);

    $this->postJson('/api/v1/webhooks', [
        'target_url' => 'http://internal.local/hook',
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['target_url']);

    $this->postJson('/api/v1/webhooks', [
        'target_url' => 'https://hooks.example.com/ok',
        'events' => ['submission.deleted'],
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['events.0']);
});

test('webhook subscription routes require auth and webhooks:manage ability', function () {
    $user = User::factory()->create();
    $readOnlyToken = $user->createToken('read-only', [TokenAbility::FormsRead->value, TokenAbility::SubmissionsRead->value])->plainTextToken;

    $this->getJson('/api/v1/webhooks')->assertUnauthorized();
    $this->postJson('/api/v1/webhooks', ['target_url' => 'https://hooks.example.com/a'])->assertUnauthorized();

    $this->getJson('/api/v1/webhooks', ['Authorization' => "Bearer {$readOnlyToken}"])->assertForbidden();
    $this->postJson('/api/v1/webhooks', ['target_url' => 'https://hooks.example.com/a'], ['Authorization' => "Bearer {$readOnlyToken}"])->assertForbidden();
    $this->json('DELETE', '/api/v1/webhooks/non-existent', [], ['Authorization' => "Bearer {$readOnlyToken}"])->assertForbidden();
});

test('POST /api/v1/webhooks enforces the per-user active subscription limit', function () {
    $user = User::factory()->create();
    $token = $user->createToken('hub', [TokenAbility::WebhooksManage->value])->plainTextToken;

    for ($i = 0; $i < WebhookSubscription::MAX_ACTIVE_PER_USER; $i++) {
        WebhookSubscription::create(['user_id' => $user->id, 'target_url' => "https://hooks.example.com/{$i}"]);
    }
    WebhookSubscription::create(['user_id' => $user->id, 'target_url' => 'https://hooks.example.com/disabled', 'is_active' => false]);

    $this->postJson('/api/v1/webhooks', ['target_url' => 'https://hooks.example.com/over'], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(422);

    WebhookSubscription::where('user_id', $user->id)->where('is_active', true)->first()->delete();

    $this->postJson('/api/v1/webhooks', ['target_url' => 'https://hooks.example.com/ok'], ['Authorization' => "Bearer {$token}"])
        ->assertCreated();
});
