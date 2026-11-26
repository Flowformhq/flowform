<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Support\TokenAbility;
use Laravel\Sanctum\Sanctum;

test('owner lists submissions newest first with decrypted data and cursor pagination across 2 pages', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Encrypted Leads',
        'slug' => 'encrypted-leads',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);

    $first = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'is_encrypted' => true,
        'data' => ['email' => 'one@example.com', 'note' => 'First'],
        'meta' => ['ip' => '192.0.2.1', 'source' => 'astro'],
    ]);

    $second = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'is_encrypted' => true,
        'data' => ['email' => 'two@example.com', 'note' => 'Second'],
        'meta' => ['ip' => '192.0.2.2', 'source' => 'nextjs'],
    ]);

    $third = Submission::create([
        'form_id' => $form->id,
        'user_id' => $owner->id,
        'status' => 'completed',
        'is_encrypted' => true,
        'data' => ['email' => 'three@example.com', 'note' => 'Third'],
        'meta' => ['ip' => '192.0.2.3', 'source' => 'wordpress'],
    ]);

    $token = $owner->createToken('poll-client', [TokenAbility::SubmissionsRead->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $page1 = $this->getJson("/api/v1/forms/{$form->uuid}/submissions?per_page=2", $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.uuid', $third->uuid)
        ->assertJsonPath('data.0.data.email', 'three@example.com')
        ->assertJsonPath('data.0.meta.source', 'wordpress')
        ->assertJsonMissingPath('data.0.meta.ip')
        ->assertJsonPath('data.1.uuid', $second->uuid)
        ->assertJsonStructure([
            'data' => [['uuid', 'status', 'data', 'meta', 'created_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ]);

    $nextCursor = $page1->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?per_page=2&cursor={$nextCursor}", $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $first->uuid)
        ->assertJsonPath('data.0.data.email', 'one@example.com')
        ->assertJsonPath('meta.next_cursor', null);
});

test('since and status filters narrow form submissions list', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Filter Form',
        'slug' => 'filter-form',
        'is_active' => true,
    ]);

    $oldCompleted = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['name' => 'Old'],
    ]);
    $oldCompleted->forceFill([
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ])->saveQuietly();

    $newDraft = Submission::create([
        'form_id' => $form->id,
        'status' => 'draft',
        'data' => ['name' => 'New Draft'],
    ]);
    $newDraft->forceFill([
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ])->saveQuietly();

    $newCompleted = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['name' => 'New Completed'],
    ]);
    $newCompleted->forceFill([
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ])->saveQuietly();

    $token = $owner->createToken('poll-client', [TokenAbility::SubmissionsRead->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];
    $since = urlencode(now()->subHour()->toIso8601String());

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?since={$since}", $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.uuid', $newCompleted->uuid)
        ->assertJsonPath('data.1.uuid', $newDraft->uuid);

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?since={$since}&status=completed", $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $newCompleted->uuid);

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?status=draft", $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $newDraft->uuid);
});

test('include=meta exposes ip address while default strips ip', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Meta Form',
        'is_active' => true,
    ]);

    Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['name' => 'Alice'],
        'meta' => ['ip' => '203.0.113.42', 'user_agent' => 'Pest/1.0'],
    ]);

    $token = $owner->createToken('meta-client', [TokenAbility::SubmissionsRead->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions", $headers)
        ->assertOk()
        ->assertJsonPath('data.0.meta.user_agent', 'Pest/1.0')
        ->assertJsonMissingPath('data.0.meta.ip');

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?include=meta", $headers)
        ->assertOk()
        ->assertJsonPath('data.0.meta.ip', '203.0.113.42')
        ->assertJsonPath('data.0.meta.user_agent', 'Pest/1.0');
});

test('user B gets 404 for user A form and unowned forms return 404', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $formA = Form::create(['user_id' => $userA->id, 'name' => 'Form A', 'is_active' => true]);
    $unownedForm = Form::create(['user_id' => null, 'name' => 'Orphan Form', 'is_active' => true]);

    $tokenB = $userB->createToken('b-client', ['*'])->plainTextToken;
    $headersB = ['Authorization' => "Bearer {$tokenB}"];

    $this->getJson("/api/v1/forms/{$formA->uuid}/submissions", $headersB)
        ->assertNotFound();

    $this->getJson("/api/v1/forms/{$unownedForm->uuid}/submissions", $headersB)
        ->assertNotFound();
});

test('submissions list requires authentication and submissions:read ability', function () {
    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Auth Form', 'is_active' => true]);

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions")
        ->assertUnauthorized();

    $wrongToken = $owner->createToken('forms-only', [TokenAbility::FormsRead->value])->plainTextToken;

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions", ['Authorization' => "Bearer {$wrongToken}"])
        ->assertForbidden();
});

test('GET /api/v1/forms lists only token owner forms when authenticated and enforces forms:read ability', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $formA1 = Form::create(['user_id' => $userA->id, 'name' => 'A Active', 'is_active' => true]);
    $formA2 = Form::create(['user_id' => $userA->id, 'name' => 'A Inactive', 'is_active' => false]);
    $formB = Form::create(['user_id' => $userB->id, 'name' => 'B Active', 'is_active' => true]);

    // Unauthenticated still sees public active forms
    $this->getJson('/api/v1/forms')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Token without forms:read -> 403
    $badToken = $userA->createToken('submissions-only', [TokenAbility::SubmissionsRead->value])->plainTextToken;
    $this->getJson('/api/v1/forms', ['Authorization' => "Bearer {$badToken}"])
        ->assertForbidden();

    app('auth')->forgetGuards();

    // Sanctum::actingAs without forms:read -> 403
    Sanctum::actingAs($userA, [TokenAbility::SubmissionsRead->value]);
    $this->getJson('/api/v1/forms')
        ->assertForbidden();

    app('auth')->forgetGuards();

    // Token with forms:read -> only userA's forms
    $goodToken = $userA->createToken('forms-read', [TokenAbility::FormsRead->value])->plainTextToken;
    $response = $this->getJson('/api/v1/forms', ['Authorization' => "Bearer {$goodToken}"])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $uuids = array_column((array) $response->json('data'), 'uuid');
    expect($uuids)->toContain($formA1->uuid, $formA2->uuid);
    expect($uuids)->not->toContain($formB->uuid);

    app('auth')->forgetGuards();

    // Invalid bearer token -> 401
    $this->getJson('/api/v1/forms', ['Authorization' => 'Bearer invalid-token'])
        ->assertUnauthorized();
});

test('submissions list clamps per_page to 100 and validates query parameters', function () {
    $owner = User::factory()->create();
    $form = Form::create(['user_id' => $owner->id, 'name' => 'Pagination Limits', 'is_active' => true]);

    $token = $owner->createToken('poll-client', [TokenAbility::SubmissionsRead->value])->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?per_page=250", $headers)
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?per_page=0", $headers)
        ->assertUnprocessable();

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions?since=not-a-date", $headers)
        ->assertUnprocessable();
});

test('listing encrypted submissions records a decryption audit entry per returned submission', function () {
    $owner = User::factory()->create();
    $form = Form::create([
        'user_id' => $owner->id,
        'name' => 'Encrypted Audit',
        'slug' => 'encrypted-audit',
        'type' => 'endpoint',
        'is_active' => true,
        'is_encrypted' => true,
    ]);
    $plain = Form::create(['user_id' => $owner->id, 'name' => 'Plain', 'slug' => 'plain-audit', 'type' => 'endpoint', 'is_active' => true]);

    $encrypted = Submission::create(['form_id' => $form->id, 'status' => 'completed', 'is_encrypted' => true, 'data' => ['email' => 'a@example.com']]);
    Submission::create(['form_id' => $plain->id, 'status' => 'completed', 'data' => ['email' => 'b@example.com']]);

    $headers = ['Authorization' => 'Bearer '.$owner->createToken('poll', [TokenAbility::SubmissionsRead->value])->plainTextToken];

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions", $headers)->assertOk();
    $this->getJson("/api/v1/forms/{$plain->uuid}/submissions", $headers)->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $owner->id,
        'action' => 'submission.decrypted',
        'auditable_type' => Submission::class,
        'auditable_id' => $encrypted->id,
    ]);
    $this->assertDatabaseCount('audit_logs', 1);
});
