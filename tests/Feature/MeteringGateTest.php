<?php

declare(strict_types=1);

use App\Filament\Widgets\FormsOverviewWidget;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

test('with metering disabled (default), non-admin user accepts submission #51 without 429', function () {
    config(['flowform.metering.enabled' => false]);

    $user = User::factory()->create([
        'is_admin' => false,
        'monthly_quota' => 50,
        'credits_balance' => 0,
    ]);

    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Unlimited OSS Form',
        'slug' => 'unlimited-oss',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    // Create 50 past submissions this month
    for ($i = 0; $i < 50; $i++) {
        Submission::create([
            'form_id' => $form->id,
            'status' => 'completed',
            'created_at' => now(),
            'data' => ['index' => $i],
        ]);
    }

    // Submission #51 should succeed because metering is disabled
    $response = $this->postJson('/api/v1/f/unlimited-oss', [
        'email' => 'submission51@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Submission received',
        ]);
});

test('with metering disabled, billing routes are not registered and return 404', function () {
    config(['flowform.metering.enabled' => false]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/billing/upgrade')
        ->assertNotFound();

    $this->post('/api/v1/zoneless/webhook')
        ->assertNotFound();
});

test('with metering enabled, user with exhausted quota and zero credits gets 429', function () {
    config(['flowform.metering.enabled' => true]);

    $user = User::factory()->create([
        'is_admin' => false,
        'monthly_quota' => 50,
        'credits_balance' => 0,
    ]);

    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Metered Form',
        'slug' => 'metered-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    // Create 50 past submissions this month
    for ($i = 0; $i < 50; $i++) {
        Submission::create([
            'form_id' => $form->id,
            'status' => 'completed',
            'created_at' => now(),
            'data' => ['index' => $i],
        ]);
    }

    $response = $this->postJson('/api/v1/f/metered-form', [
        'email' => 'submission51@example.com',
    ]);

    $response->assertStatus(429)
        ->assertJson([
            'success' => false,
        ]);
});

test('FormsOverviewWidget respects metering config', function () {
    $user = User::factory()->create([
        'is_admin' => false,
        'monthly_quota' => 50,
        'credits_balance' => 500,
    ]);

    Auth::login($user);

    // With metering disabled
    config(['flowform.metering.enabled' => false]);
    $widget = new class extends FormsOverviewWidget
    {
        /**
         * @return array<int, \Filament\Widgets\StatsOverviewWidget\Stat>
         */
        public function testGetStats(): array
        {
            return $this->getStats();
        }
    };

    $statsDisabled = $widget->testGetStats();
    $labelsDisabled = array_map(fn ($s) => $s->getLabel(), $statsDisabled);

    expect($labelsDisabled)->not->toContain('Your Plan');
    expect($labelsDisabled)->not->toContain('Pay-As-You-Go Credits');
    expect($labelsDisabled)->toContain('Active Forms');
    expect($labelsDisabled)->toContain('Monthly Ingest');

    // With metering enabled
    config(['flowform.metering.enabled' => true]);
    $statsEnabled = $widget->testGetStats();
    $labelsEnabled = array_map(fn ($s) => $s->getLabel(), $statsEnabled);

    expect($labelsEnabled)->toContain('Your Plan');
    expect($labelsEnabled)->toContain('Pay-As-You-Go Credits');
    expect($labelsEnabled)->toContain('Active Forms');
    expect($labelsEnabled)->toContain('Monthly Ingest');
});
