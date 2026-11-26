<?php

declare(strict_types=1);

use App\Filament\Pages\ApiTokensPage;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Support\TokenAbility;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
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

    expect(fn () => Livewire::test(ApiTokensPage::class)->callTableAction('revoke', $bobToken))
        ->toThrow(ActionNotResolvableException::class);

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

/** @return Collection<int, Illuminate\Routing\Route> */
function tokenAuthenticatedApiRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') && in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->values();
}

test('every token-authenticated API route enforces a token ability', function () {
    $unguarded = tokenAuthenticatedApiRoutes()
        ->reject(fn ($route) => $route->getName() === 'api.mcp')
        ->reject(fn ($route) => collect($route->gatherMiddleware())->contains(
            fn ($middleware) => is_string($middleware) && (str_starts_with($middleware, 'ability:') || str_starts_with($middleware, 'abilities:'))
        ))
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect(tokenAuthenticatedApiRoutes())->not->toBeEmpty()
        ->and($unguarded)->toBe([]);
});

test('legacy wildcard token is never rejected for missing abilities on any token-authenticated route', function () {
    $user = User::factory()->create();
    $form = Form::create(['user_id' => $user->id, 'name' => 'Legacy Form', 'is_active' => true]);
    $submission = Submission::create(['form_id' => $form->id, 'user_id' => $user->id]);
    $headers = ['Authorization' => 'Bearer '.$user->createToken('legacy-token')->plainTextToken];

    foreach (tokenAuthenticatedApiRoutes() as $route) {
        $method = collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();
        $uri = '/'.str_replace('{uuid}', $submission->uuid, $route->uri());

        $status = $this->json($method, $uri, [], $headers)->status();

        expect($status)->not->toBeIn([401, 403], "{$method} {$uri} returned {$status}");
    }
});

test('expired token returns 401 and unexpired token returns 200', function () {
    $user = User::factory()->create();
    $form = Form::create(['user_id' => $user->id, 'name' => 'Expiry Form', 'is_active' => true]);

    $validToken = $user->createToken('Valid', [TokenAbility::FormsRead->value, TokenAbility::SubmissionsRead->value], now()->addDay())->plainTextToken;
    $expiredToken = $user->createToken('Expired', [TokenAbility::FormsRead->value, TokenAbility::SubmissionsRead->value], now()->subMinute())->plainTextToken;

    $this->getJson('/api/v1/forms', ['Authorization' => "Bearer {$validToken}"])
        ->assertOk();

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions", ['Authorization' => "Bearer {$validToken}"])
        ->assertOk();

    app('auth')->forgetGuards();

    $this->getJson('/api/v1/forms', ['Authorization' => "Bearer {$expiredToken}"])
        ->assertUnauthorized();

    $this->getJson("/api/v1/forms/{$form->uuid}/submissions", ['Authorization' => "Bearer {$expiredToken}"])
        ->assertUnauthorized();
});

test('UI token creation supports expiry presets with 1 year default and displays expires_at and last_used_at columns', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ApiTokensPage::class)
        ->mountAction('create_token')
        ->assertActionDataSet([
            'abilities' => [],
            'expires_in' => '365',
        ])
        ->assertTableColumnExists('expires_at')
        ->assertTableColumnExists('last_used_at');

    // Default (1 year)
    Livewire::test(ApiTokensPage::class)
        ->callAction('create_token', data: [
            'name' => 'Default One Year',
            'abilities' => [TokenAbility::FormsRead->value],
        ])
        ->assertActionMounted('revealToken');

    $oneYearToken = PersonalAccessToken::where('name', 'Default One Year')->firstOrFail();
    expect($oneYearToken->expires_at)->not->toBeNull();
    expect(abs(now()->diffInDays($oneYearToken->expires_at) - 365))->toBeLessThanOrEqual(2);

    // 30 days
    Livewire::test(ApiTokensPage::class)
        ->callAction('create_token', data: [
            'name' => 'Thirty Days',
            'abilities' => [TokenAbility::FormsRead->value],
            'expires_in' => '30',
        ])
        ->assertActionMounted('revealToken');

    $thirtyDayToken = PersonalAccessToken::where('name', 'Thirty Days')->firstOrFail();
    expect(abs(now()->diffInDays($thirtyDayToken->expires_at) - 30))->toBeLessThanOrEqual(1);

    // No expiry
    Livewire::test(ApiTokensPage::class)
        ->callAction('create_token', data: [
            'name' => 'Never Expires',
            'abilities' => [TokenAbility::FormsRead->value],
            'expires_in' => 'never',
        ])
        ->assertActionMounted('revealToken');

    $neverToken = PersonalAccessToken::where('name', 'Never Expires')->firstOrFail();
    expect($neverToken->expires_at)->toBeNull();
});

test('token:create artisan command supports --expires and rejects non-positive values', function () {
    $user = User::factory()->create(['email' => 'cli-expiry@example.com']);

    $exitDefault = Artisan::call('token:create', [
        'name' => 'cli-no-expiry',
        'email' => $user->email,
    ]);
    expect($exitDefault)->toBe(0);
    expect(PersonalAccessToken::where('name', 'cli-no-expiry')->firstOrFail()->expires_at)->toBeNull();

    $exit30 = Artisan::call('token:create', [
        'name' => 'cli-30d',
        'email' => $user->email,
        '--expires' => '30',
    ]);
    expect($exit30)->toBe(0);
    $token30 = PersonalAccessToken::where('name', 'cli-30d')->firstOrFail();
    expect($token30->expires_at)->not->toBeNull();
    expect(abs(now()->diffInDays($token30->expires_at) - 30))->toBeLessThanOrEqual(1);

    foreach (['0', '-5', 'abc'] as $invalid) {
        $exitInvalid = Artisan::call('token:create', [
            'name' => "cli-bad-{$invalid}",
            'email' => $user->email,
            '--expires' => $invalid,
        ]);
        expect($exitInvalid)->toBe(1);
        expect(PersonalAccessToken::where('name', "cli-bad-{$invalid}")->exists())->toBeFalse();
    }
});
