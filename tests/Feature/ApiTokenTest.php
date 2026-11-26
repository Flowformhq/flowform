<?php

declare(strict_types=1);

use App\Filament\Pages\ApiTokensPage;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Support\TokenAbility;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

test('Livewire creates scoped submissions:read token shown once, allows GET and blocks POST with 403', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $form = Form::create(['user_id' => $user->id, 'name' => 'Scoped Form', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'user_id' => $user->id]);

    $this->actingAs($user);

    $this->get(ApiTokensPage::getUrl())->assertOk();

    Livewire::test(ApiTokensPage::class)
        ->callAction('create_token', data: [
            'name' => '',
            'abilities' => [],
        ])
        ->assertHasActionErrors(['name' => 'required', 'abilities' => 'required']);

    $livewire = Livewire::test(ApiTokensPage::class)
        ->callAction('create_token', data: [
            'name' => 'Read Only Integration',
            'abilities' => [TokenAbility::SubmissionsRead->value],
        ])
        ->assertActionMounted('revealToken');

    $plainTextToken = (string) $livewire->get('mountedActions.0.arguments.plainTextToken');
    expect($plainTextToken)->toContain('|');

    $livewire->assertMountedActionModalSee($plainTextToken);

    Livewire::test(ApiTokensPage::class)
        ->assertActionNotMounted('revealToken')
        ->assertDontSee($plainTextToken);

    app('auth')->forgetGuards();

    $headers = ['Authorization' => "Bearer {$plainTextToken}"];

    $this->getJson("/api/v1/submissions/{$submission->uuid}", $headers)
        ->assertOk()
        ->assertJsonPath('data.uuid', $submission->uuid);

    $this->postJson('/api/v1/submissions', ['form_uuid' => $form->uuid], $headers)
        ->assertForbidden();

    $this->getJson('/api/v1/compliance/gdpr/export', $headers)
        ->assertForbidden();
});

test('create token form pre-selects no abilities', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ApiTokensPage::class)
        ->mountAction('create_token')
        ->assertActionDataSet(['abilities' => []]);
});

test('revoked token returns 401 Unauthorized', function () {
    $user = User::factory()->create();
    $form = Form::create(['user_id' => $user->id, 'name' => 'Revoke Form', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'user_id' => $user->id]);

    $created = $user->createToken('Temp Integration', [TokenAbility::SubmissionsRead->value]);
    $plainTextToken = $created->plainTextToken;

    $this->actingAs($user);

    Livewire::test(ApiTokensPage::class)
        ->callTableAction('revoke', $created->accessToken)
        ->assertHasNoTableActionErrors();

    expect(PersonalAccessToken::find($created->accessToken->id))->toBeNull();

    app('auth')->forgetGuards();

    $this->getJson("/api/v1/submissions/{$submission->uuid}", ['Authorization' => "Bearer {$plainTextToken}"])
        ->assertUnauthorized();
});

test('user only sees and can revoke their own tokens', function () {
    $alice = User::factory()->create(['is_admin' => false]);
    $bob = User::factory()->create(['is_admin' => false]);

    $aliceToken = $alice->createToken('Alice Token', [TokenAbility::FormsRead->value])->accessToken;
    $bobToken = $bob->createToken('Bob Token', [TokenAbility::SubmissionsRead->value])->accessToken;

    $this->actingAs($alice);

    Livewire::test(ApiTokensPage::class)
        ->assertCanSeeTableRecords([$aliceToken])
        ->assertCanNotSeeTableRecords([$bobToken]);

    try {
        Livewire::test(ApiTokensPage::class)
            ->callTableAction('revoke', $bobToken);
    } catch (Throwable) {
        // Foreign record should not be resolvable or deletable by another tenant
    }

    expect(PersonalAccessToken::find($bobToken->id))->not->toBeNull();
    expect(PersonalAccessToken::find($aliceToken->id))->not->toBeNull();
});

test('legacy wildcard ["*"] token still works on submissions and compliance routes', function () {
    $user = User::factory()->create();
    $form = Form::create(['user_id' => $user->id, 'name' => 'Wildcard Form', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'user_id' => $user->id]);

    $legacyToken = $user->createToken('legacy-token')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$legacyToken}"];

    $this->getJson("/api/v1/submissions/{$submission->uuid}", $headers)
        ->assertOk();

    $this->postJson('/api/v1/submissions', ['form_uuid' => $form->uuid], $headers)
        ->assertCreated();

    $this->getJson('/api/v1/compliance/gdpr/export', $headers)
        ->assertOk();
});

test('compliance routes allow tokens with all explicit abilities and deny partial tokens', function () {
    $user = User::factory()->create();

    $fullExplicitToken = $user->createToken('Full Scoped', TokenAbility::values())->plainTextToken;
    $partialToken = $user->createToken('Partial Scoped', [
        TokenAbility::SubmissionsRead->value,
        TokenAbility::FormsRead->value,
    ])->plainTextToken;

    $partialHeaders = ['Authorization' => "Bearer {$partialToken}"];

    $this->getJson('/api/v1/compliance/gdpr/export', $partialHeaders)
        ->assertForbidden();

    $this->postJson('/api/v1/compliance/gdpr/erasure', ['email' => 'none@example.com'], $partialHeaders)
        ->assertForbidden();

    app('auth')->forgetGuards();

    $this->getJson('/api/v1/compliance/gdpr/export', ['Authorization' => "Bearer {$fullExplicitToken}"])
        ->assertOk();

    Sanctum::actingAs($user, ['*']);
    $this->getJson('/api/v1/compliance/gdpr/export')->assertOk();

    app('auth')->forgetGuards();

    $this->actingAs($user)
        ->getJson('/api/v1/compliance/gdpr/export')
        ->assertOk();
});

test('token:create artisan command supports --abilities and defaults to wildcard', function () {
    $user = User::factory()->create(['email' => 'cli-owner@example.com']);

    $exitDefault = Artisan::call('token:create', [
        'name' => 'default-cli',
        'email' => $user->email,
    ]);
    expect($exitDefault)->toBe(0);

    $defaultToken = PersonalAccessToken::where('name', 'default-cli')->firstOrFail();
    expect($defaultToken->abilities)->toBe(['*']);

    $exitScoped = Artisan::call('token:create', [
        'name' => 'scoped-cli',
        'email' => $user->email,
        '--abilities' => 'submissions:read,webhooks:manage',
    ]);
    expect($exitScoped)->toBe(0);

    $scopedToken = PersonalAccessToken::where('name', 'scoped-cli')->firstOrFail();
    expect($scopedToken->abilities)->toBe(['submissions:read', 'webhooks:manage']);

    $exitInvalid = Artisan::call('token:create', [
        'name' => 'bad-cli',
        'email' => $user->email,
        '--abilities' => 'not:an:ability',
    ]);
    expect($exitInvalid)->toBe(1);
    expect(PersonalAccessToken::where('name', 'bad-cli')->exists())->toBeFalse();

    $exitEmpty = Artisan::call('token:create', [
        'name' => 'empty-cli',
        'email' => $user->email,
        '--abilities' => ' , ',
    ]);
    expect($exitEmpty)->toBe(1);
    expect(PersonalAccessToken::where('name', 'empty-cli')->exists())->toBeFalse();
});
