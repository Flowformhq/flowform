<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\MailSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MailSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Mail Transport & SMTP';

    protected static ?string $navigationLabel = 'Mail Transport';

    protected static ?string $slug = 'mail-settings';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.mail-settings';

    public const PRESETS = [
        'resend' => [
            'name' => 'Resend',
            'driver' => 'resend',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Modern developer email platform (3,000 free emails/month). Enter your API key.',
            'url' => 'https://resend.com/api-keys',
        ],
        'brevo' => [
            'name' => 'Brevo',
            'driver' => 'smtp',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Free tier offers 300 emails/day. Generate SMTP credentials in your Brevo dashboard.',
            'url' => 'https://app.brevo.com/settings/keys/smtp',
        ],
        'postmark' => [
            'name' => 'Postmark',
            'driver' => 'postmark',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Strict deliverability with high inbox rates. Enter your Server API Token.',
            'url' => 'https://account.postmarkapp.com/servers',
        ],
        'ses' => [
            'name' => 'Amazon SES',
            'driver' => 'ses',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Cost-effective high-volume delivery on AWS. Configure AWS access key, secret and region.',
            'url' => 'https://console.aws.amazon.com/ses/',
        ],
        'mailgun' => [
            'name' => 'Mailgun',
            'driver' => 'smtp',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Reliable sending via Mailgun SMTP relay. Find credentials in Sending > Domains.',
            'url' => 'https://app.mailgun.com/app/sending/domains',
        ],
        'smtp2go' => [
            'name' => 'SMTP2GO',
            'driver' => 'smtp',
            'host' => 'mail.smtp2go.com',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Free tier provides 1,000 emails/month. Generate an SMTP user in Settings > Users.',
            'url' => 'https://app.smtp2go.com/settings/users',
        ],
        'smtp' => [
            'name' => 'Generic SMTP',
            'driver' => 'smtp',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Connect to any standard SMTP server (cPanel, Postfix, Mailtrap, Hostinger).',
            'url' => null,
        ],
        'log' => [
            'name' => 'Local Log',
            'driver' => 'log',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'hint' => 'Writes messages to storage/logs/laravel.log without dispatching external emails.',
            'url' => null,
        ],
    ];

    public ?string $preset = null;

    public string $driver = 'smtp';

    public ?string $host = '';

    public int $port = 587;

    public ?string $encryption = 'tls';

    public ?string $username = '';

    public ?string $password = '';

    public ?string $api_key = '';

    public ?string $region = 'us-east-1';

    public ?string $from_address = '';

    public ?string $from_name = 'FlowForm Intake';

    public bool $is_active = true;

    public string $test_recipient = '';

    public bool $showPassword = false;

    public bool $showApiKey = false;

    public ?array $testResult = null;

    public function mount(): void
    {
        $setting = MailSetting::getActive() ?? MailSetting::latest()->first();

        if ($setting) {
            $this->driver = $setting->driver ?: 'smtp';
            $this->host = $setting->host ?? '';
            $this->port = $setting->port ?? 587;
            $this->encryption = $setting->encryption ?? 'tls';
            $this->username = $setting->username ?? '';
            $this->password = $setting->password ?? '';
            $this->api_key = $setting->api_key ?? '';
            $this->region = $setting->region ?? 'us-east-1';
            $this->from_address = $setting->from_address ?? config('mail.from.address', 'noreply@flowformhq.com');
            $this->from_name = $setting->from_name ?? config('mail.from.name', 'FlowForm Intake');
            $this->is_active = $setting->is_active;
            $this->preset = $this->detectPreset($this->driver, $this->host);
        } else {
            $this->from_address = config('mail.from.address', 'noreply@flowformhq.com');
            $this->from_name = config('mail.from.name', 'FlowForm Intake');
            $this->preset = 'smtp';
        }

        if (empty($this->test_recipient)) {
            $this->test_recipient = auth()->user()?->email ?? 'test@example.com';
        }
    }

    public function selectPreset(string $presetKey): void
    {
        $key = strtolower($presetKey);
        if ($key === 'generic') {
            $key = 'smtp';
        }

        $this->preset = $key;

        if (! isset(self::PRESETS[$key])) {
            return;
        }

        $preset = self::PRESETS[$key];
        $this->driver = $preset['driver'];
        $this->host = $preset['host'];
        $this->port = $preset['port'];
        $this->encryption = $preset['encryption'];
    }

    public function selectDriver(string $driver): void
    {
        $this->driver = $driver;
        $this->preset = $this->detectPreset($driver, $this->host);
    }

    protected function detectPreset(string $driver, ?string $host): string
    {
        if ($driver === 'smtp') {
            if ($host && str_contains($host, 'brevo')) {
                return 'brevo';
            }
            if ($host && str_contains($host, 'mailgun')) {
                return 'mailgun';
            }
            if ($host && str_contains($host, 'smtp2go')) {
                return 'smtp2go';
            }

            return 'smtp';
        }

        return $driver;
    }

    public function toggleShowPassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function toggleShowApiKey(): void
    {
        $this->showApiKey = ! $this->showApiKey;
    }

    protected function getViewData(): array
    {
        return [
            'presets' => self::PRESETS,
            'preset' => $this->preset,
            'driver' => $this->driver,
            'is_active' => $this->is_active,
            'showPassword' => $this->showPassword,
            'showApiKey' => $this->showApiKey,
            'testResult' => $this->testResult,
        ];
    }

    public function save(): void
    {
        $this->validate([
            'driver' => ['required', 'string', 'in:smtp,ses,resend,postmark,log'],
            'from_address' => ['nullable', 'email'],
            'port' => ['integer', 'min:1', 'max:65535'],
        ]);

        $isDemo = str_contains(request()->getHost(), 'demo');

        if ($isDemo) {
            Notification::make()
                ->title('Demo Configuration Simulated')
                ->body('Mail transport preferences updated in your live demo session.')
                ->success()
                ->send();

            return;
        }

        $setting = MailSetting::latest()->first() ?? new MailSetting;

        $setting->driver = $this->driver;
        $setting->host = $this->host ?: null;
        $setting->port = $this->port;
        $setting->encryption = $this->encryption ?: null;
        $setting->username = $this->username ?: null;
        if (! empty($this->password)) {
            $setting->password = $this->password;
        }
        if (! empty($this->api_key)) {
            $setting->api_key = $this->api_key;
        }
        $setting->region = $this->region ?: 'us-east-1';
        $setting->from_address = $this->from_address ?: null;
        $setting->from_name = $this->from_name ?: null;
        $setting->is_active = $this->is_active;
        $setting->save();

        // Apply dynamically to runtime config
        $setting->applyToConfig();

        Notification::make()
            ->title('Mail settings saved successfully!')
            ->body('Transactional configuration active for form alerts.')
            ->success()
            ->send();
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'test_recipient' => ['required', 'email'],
        ]);

        $isDemo = str_contains(request()->getHost(), 'demo');

        if ($isDemo) {
            $this->testResult = null;

            Notification::make()
                ->title('Demo Instance')
                ->body('Test sending is disabled on the demo instance.')
                ->info()
                ->send();

            return;
        }

        $setting = MailSetting::latest()->first();

        if (! $setting) {
            $setting = new MailSetting([
                'driver' => $this->driver,
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'username' => $this->username,
                'password' => $this->password,
                'api_key' => $this->api_key,
                'region' => $this->region,
                'from_address' => $this->from_address,
                'from_name' => $this->from_name,
                'is_active' => true,
            ]);
        }

        $result = $setting->sendTestEmail($this->test_recipient);
        $this->testResult = [
            'success' => $result['success'],
            'message' => $result['message'],
            'timestamp' => now()->format('H:i:s'),
        ];

        if ($result['success']) {
            Notification::make()
                ->title('Test Email Dispatched')
                ->body($result['message'])
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Test Email Delivery Failed')
                ->body($result['message'])
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
