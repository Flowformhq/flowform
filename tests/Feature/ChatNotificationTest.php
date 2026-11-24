<?php

declare(strict_types=1);

use App\Filament\Resources\Forms\Pages\EditForm;
use App\Filament\Resources\Forms\RelationManagers\NotificationChannelsRelationManager;
use App\Jobs\SendChatNotification;
use App\Models\Form;
use App\Models\NotificationChannel;
use App\Models\Submission;
use App\Models\User;
use App\Rules\ValidWebhookUrl;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['flowform.webhooks.allow_private_targets' => true]);
    ValidWebhookUrl::$dnsResolver = null;
});

test('Slack: sends Block Kit payload with header, fields, and context', function () {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Contact Sales',
        'slug' => 'contact-sales',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => [
            'webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00',
        ],
        'is_active' => true,
    ]);

    $response = $this->postJson('/f/contact-sales', [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'company' => 'Acme Corp',
    ]);

    $response->assertOk();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://hooks.slack.com/services/T00/B00/X00') {
            return false;
        }

        $body = $request->data();
        $blocks = $body['blocks'] ?? [];

        // 1. Header block
        $header = $blocks[0] ?? [];
        $hasHeader = ($header['type'] ?? '') === 'header'
            && str_contains($header['text']['text'] ?? '', 'Contact Sales');

        // 2. Section block with fields
        $section = $blocks[1] ?? [];
        $hasSection = ($section['type'] ?? '') === 'section'
            && is_array($section['fields'] ?? null)
            && count($section['fields']) === 3;

        // 3. Context block
        $context = $blocks[2] ?? [];
        $hasContext = ($context['type'] ?? '') === 'context'
            && str_contains($context['elements'][0]['text'] ?? '', 'Submission:');

        return $hasHeader && $hasSection && $hasContext;
    });
});

test('Slack: respects 20 fields max and 500 chars truncation limit', function () {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Massive Form',
        'slug' => 'massive-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => [
            'webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00',
        ],
        'is_active' => true,
    ]);

    $longValue = str_repeat('A', 800);
    $data = [];
    for ($i = 1; $i <= 30; $i++) {
        $data["field_{$i}"] = $longValue;
    }

    $submission = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => $data,
    ]);

    (new SendChatNotification($channel, $submission))->handle();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $blocks = $body['blocks'] ?? [];

        $fieldElements = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'section' && isset($block['fields'])) {
                foreach ($block['fields'] as $f) {
                    $fieldElements[] = $f;
                }
            }
        }

        // Must be capped at 20 fields
        expect(count($fieldElements))->toBe(20);

        // Value must be truncated to 500 characters
        $firstFieldText = $fieldElements[0]['text'] ?? '';
        expect(str_contains($firstFieldText, str_repeat('A', 500)))->toBeTrue()
            ->and(str_contains($firstFieldText, str_repeat('A', 501)))->toBeFalse();

        return true;
    });
});

test('Discord: sends embeds payload with title, fields, and timestamp', function () {
    Http::fake([
        'https://discord.com/*' => Http::response(['id' => '123'], 204),
    ]);

    $form = Form::create([
        'name' => 'Discord Form',
        'slug' => 'discord-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_DISCORD,
        'config' => [
            'webhook_url' => 'https://discord.com/api/webhooks/123/xyz',
        ],
        'is_active' => true,
    ]);

    $response = $this->postJson('/f/discord-form', [
        'user' => 'Gamer123',
        'feedback' => 'Love this tool!',
    ]);

    $response->assertOk();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://discord.com/api/webhooks/123/xyz') {
            return false;
        }

        $body = $request->data();
        $embed = $body['embeds'][0] ?? [];

        expect($embed['title'])->toBe('New Submission: Discord Form')
            ->and($embed['fields'])->toHaveCount(2)
            ->and($embed['fields'][0]['name'])->toBe('user')
            ->and($embed['fields'][0]['value'])->toBe('Gamer123')
            ->and($embed['fields'][0]['inline'])->toBeTrue()
            ->and($embed['timestamp'])->not->toBeEmpty();

        return true;
    });
});

test('Discord: caps values at 1024 chars and the whole embed at 6000 chars', function () {
    Http::fake([
        'https://discord.com/*' => Http::response(['id' => '123'], 204),
    ]);

    $form = Form::create([
        'name' => 'Discord Big Form',
        'slug' => 'discord-big-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_DISCORD,
        'config' => [
            'webhook_url' => 'https://discord.com/api/webhooks/123/xyz',
        ],
        'is_active' => true,
    ]);

    $longValue = str_repeat('D', 1500);
    $data = [];
    for ($i = 1; $i <= 35; $i++) {
        $data["discord_field_{$i}"] = $longValue;
    }

    $submission = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => $data,
    ]);

    (new SendChatNotification($channel, $submission))->handle();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $fields = $body['embeds'][0]['fields'] ?? [];

        $total = mb_strlen($body['embeds'][0]['title'])
            + array_sum(array_map(fn ($f) => mb_strlen($f['name']) + mb_strlen($f['value']), $fields));

        expect(strlen($fields[0]['value']))->toBe(1024);
        expect(count($fields))->toBeLessThan(25);
        expect($total)->toBeLessThanOrEqual(6000);
        expect($body['allowed_mentions'])->toBe(['parse' => []]);

        return true;
    });
});

test('Telegram: sends HTML formatted message with escaped HTML characters', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Telegram & Alerts <v1>',
        'slug' => 'telegram-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_TELEGRAM,
        'config' => [
            'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'chat_id' => '-100987654321',
        ],
        'is_active' => true,
    ]);

    $response = $this->postJson('/f/telegram-form', [
        'name' => 'Neo <The One>',
        'bio' => 'Tom & Jerry "cool" <script>alert(1)</script>',
    ]);

    $response->assertOk();

    Http::assertSent(function (Request $request) {
        if ($request->url() !== 'https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/sendMessage') {
            return false;
        }

        $body = $request->data();

        expect($body['chat_id'])->toBe('-100987654321')
            ->and($body['parse_mode'])->toBe('HTML');

        $text = $body['text'] ?? '';

        // Asserts HTML header and escaping
        expect($text)->toContain('<b>New Submission: Telegram &amp; Alerts &lt;v1&gt;</b>')
            ->and($text)->toContain('<b>name:</b> Neo &lt;The One&gt;')
            ->and($text)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->and($text)->not->toContain('<script>');

        return true;
    });
});

test('encrypted form sends placeholders instead of plaintext values', function () {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
        'https://discord.com/*' => Http::response(['id' => '123'], 204),
        'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $form = Form::create([
        'name' => 'Confidential HIPAA Form',
        'slug' => 'confidential-form',
        'type' => 'endpoint',
        'is_encrypted' => true,
        'is_active' => true,
    ]);

    $slack = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'],
        'is_active' => true,
    ]);

    $discord = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_DISCORD,
        'config' => ['webhook_url' => 'https://discord.com/api/webhooks/1/2'],
        'is_active' => true,
    ]);

    $telegram = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_TELEGRAM,
        'config' => ['bot_token' => '123:abc', 'chat_id' => '999'],
        'is_active' => true,
    ]);

    $secretMedicalInfo = 'Patient diagnosed with secret condition 12345';
    $response = $this->postJson('/f/confidential-form', [
        'ssn' => '000-12-3456',
        'medical_notes' => $secretMedicalInfo,
    ]);

    $response->assertOk();

    // Assert Slack receives [encrypted]
    Http::assertSent(function (Request $request) use ($secretMedicalInfo) {
        if ($request->url() === 'https://hooks.slack.com/services/T/B/X') {
            $raw = json_encode($request->data());
            expect($raw)->not->toContain($secretMedicalInfo)
                ->and($raw)->toContain('[encrypted]');

            return true;
        }

        return false;
    });

    // Assert Discord receives [encrypted]
    Http::assertSent(function (Request $request) use ($secretMedicalInfo) {
        if ($request->url() === 'https://discord.com/api/webhooks/1/2') {
            $raw = json_encode($request->data());
            expect($raw)->not->toContain($secretMedicalInfo)
                ->and($raw)->toContain('[encrypted]');

            return true;
        }

        return false;
    });

    // Assert Telegram receives [encrypted]
    Http::assertSent(function (Request $request) use ($secretMedicalInfo) {
        if (str_starts_with($request->url(), 'https://api.telegram.org')) {
            $raw = json_encode($request->data());
            expect($raw)->not->toContain($secretMedicalInfo)
                ->and($raw)->toContain('[encrypted]');

            return true;
        }

        return false;
    });
});

test('inactive channel sends nothing', function () {
    Http::fake();

    $form = Form::create([
        'name' => 'Inactive Test Form',
        'slug' => 'inactive-test-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'],
        'is_active' => false,
    ]);

    $response = $this->postJson('/f/inactive-test-form', [
        'name' => 'Bob',
    ]);

    $response->assertOk();
    Http::assertNothingSent();
});

test('channel failure does not affect submission response', function () {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('Service Unavailable', 503),
    ]);

    $form = Form::create([
        'name' => 'Failing Channel Form',
        'slug' => 'failing-channel-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'],
        'is_active' => true,
    ]);

    $response = $this->postJson('/f/failing-channel-form', [
        'name' => 'Charlie',
    ]);

    // Submission endpoint must still succeed with 200
    $response->assertOk();

    $this->assertDatabaseHas('submissions', [
        'form_id' => $form->id,
        'status' => 'completed',
    ]);
});

test('bot token and webhook URL are encrypted at rest in database', function () {
    $form = Form::create([
        'name' => 'Encryption Check Form',
        'slug' => 'encryption-check-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $rawSecretWebhook = 'https://hooks.slack.com/services/SUPER_SECRET_HOOK';
    $rawSecretBotToken = '987654321:TOP_SECRET_BOT_TOKEN';

    $slackChannel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => $rawSecretWebhook],
        'is_active' => true,
    ]);

    $telegramChannel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_TELEGRAM,
        'config' => ['bot_token' => $rawSecretBotToken, 'chat_id' => '12345'],
        'is_active' => true,
    ]);

    // Fetch raw value from DB table without Eloquent decryption
    $rawSlackConfig = DB::table('notification_channels')->where('id', $slackChannel->id)->value('config');
    $rawTelegramConfig = DB::table('notification_channels')->where('id', $telegramChannel->id)->value('config');

    // Assert raw DB value is encrypted ciphertext and does NOT contain plaintext secrets
    expect($rawSlackConfig)->not->toContain('SUPER_SECRET_HOOK')
        ->and($rawSlackConfig)->not->toContain($rawSecretWebhook)
        ->and($rawTelegramConfig)->not->toContain('TOP_SECRET_BOT_TOKEN')
        ->and($rawTelegramConfig)->not->toContain($rawSecretBotToken);

    // Assert fresh Eloquent access transparently decrypts
    expect($slackChannel->fresh()->config['webhook_url'])->toBe($rawSecretWebhook)
        ->and($telegramChannel->fresh()->config['bot_token'])->toBe($rawSecretBotToken);
});

test('private network targets and disallowed hosts are blocked', function () {
    Http::fake();
    config(['flowform.webhooks.allow_private_targets' => false]);

    $form = Form::create([
        'name' => 'SSRF Test Form',
        'slug' => 'ssrf-test-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $disallowedChannel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://malicious-site.example.com/hook'],
        'is_active' => true,
    ]);

    $submission = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['test' => 'ssrf'],
    ]);

    (new SendChatNotification($disallowedChannel, $submission))->handle();

    Http::assertNothingSent();
});

test('Filament: sendTest action successfully triggers test notification', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $form = Form::create([
        'user_id' => $user->id,
        'name' => 'Filament Test Form',
        'slug' => 'filament-test-form',
        'type' => 'endpoint',
        'is_active' => true,
    ]);

    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/T/B/X'],
        'is_active' => true,
    ]);

    Http::fake([
        'https://hooks.slack.com/*' => Http::response(['ok' => true], 200),
    ]);

    Livewire::test(NotificationChannelsRelationManager::class, [
        'ownerRecord' => $form,
        'pageClass' => EditForm::class,
    ])
        ->callTableAction('sendTest', $channel)
        ->assertHasNoTableActionErrors();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://hooks.slack.com/services/T/B/X'
            && str_contains(json_encode($request->data()), 'FlowForm chat integration test');
    });
});

test('Slack: escapes control sequences so submitters cannot ping channels or inject links', function () {
    Http::fake(['https://hooks.slack.com/*' => Http::response(['ok' => true], 200)]);

    $form = Form::create(['name' => 'Slack Escape', 'slug' => 'slack-escape', 'type' => 'endpoint', 'is_active' => true]);
    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_SLACK,
        'config' => ['webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00'],
        'is_active' => true,
    ]);
    $submission = Submission::create([
        'form_id' => $form->id,
        'status' => 'completed',
        'data' => ['message' => '<!channel> see <https://evil.example|invoice> & pay'],
    ]);

    (new SendChatNotification($channel, $submission))->handle();

    Http::assertSent(function (Request $request) {
        $text = $request->data()['blocks'][1]['fields'][0]['text'];

        return str_contains($text, '&lt;!channel&gt; see &lt;https://evil.example|invoice&gt; &amp; pay')
            && ! str_contains($text, '<!channel>');
    });
});

test('Telegram: long submissions stay under the message limit without cutting HTML tags', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $form = Form::create(['name' => 'Telegram Long', 'slug' => 'telegram-long', 'type' => 'endpoint', 'is_active' => true]);
    $channel = $form->notificationChannels()->create([
        'type' => NotificationChannel::TYPE_TELEGRAM,
        'config' => ['bot_token' => '123456:ABC', 'chat_id' => '42'],
        'is_active' => true,
    ]);
    $data = [];
    for ($i = 1; $i <= 25; $i++) {
        $data["field_{$i}"] = str_repeat('<', 400);
    }
    $submission = Submission::create(['form_id' => $form->id, 'status' => 'completed', 'data' => $data]);

    (new SendChatNotification($channel, $submission))->handle();

    Http::assertSent(function (Request $request) {
        $text = $request->data()['text'];

        return mb_strlen($text) <= 4096
            && substr_count($text, '<b>') === substr_count($text, '</b>')
            && substr_count($text, '<i>') === substr_count($text, '</i>')
            && str_contains($text, '(more fields omitted)')
            && str_ends_with($text, '</i>');
    });
});
