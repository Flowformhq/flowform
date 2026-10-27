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
        } else {
            $this->from_address = config('mail.from.address', 'noreply@flowformhq.com');
            $this->from_name = config('mail.from.name', 'FlowForm Intake');
        }

        if (empty($this->test_recipient)) {
            $this->test_recipient = auth()->user()?->email ?? 'test@example.com';
        }
    }

    public function selectDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    public function toggleShowPassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function toggleShowApiKey(): void
    {
        $this->showApiKey = ! $this->showApiKey;
    }

    public function save(): void
    {
        $this->validate([
            'driver' => ['required', 'string', 'in:smtp,ses,resend,postmark,log'],
            'from_address' => ['nullable', 'email'],
            'port' => ['integer', 'min:1', 'max:65535'],
        ]);

        $isDemo = str_contains(request()->getHost(), 'demo') || request()->has('demo');

        if ($isDemo) {
            Notification::make()
                ->title('Demo Configuration Simulated')
                ->body('Mail transport preferences updated in your live demo session.')
                ->success()
                ->send();

            return;
        }

        $setting = MailSetting::latest()->first() ?? new MailSetting();

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

        $isDemo = str_contains(request()->getHost(), 'demo') || request()->has('demo');

        if ($isDemo) {
            $this->testResult = [
                'success' => true,
                'message' => "Diagnostic probe dispatched to {$this->test_recipient} via {$this->driver}. Sandbox handshake verified: 250 2.0.0 OK (Simulated Latency: 46ms).",
                'timestamp' => now()->format('H:i:s'),
            ];

            Notification::make()
                ->title('Diagnostic Probe Verified')
                ->body($this->testResult['message'])
                ->success()
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
