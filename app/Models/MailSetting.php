<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class MailSetting extends Model
{
    protected $fillable = [
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'api_key',
        'region',
        'from_address',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function setPasswordAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['password'] = null;
            return;
        }

        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getApiKeyAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function setApiKeyAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['api_key'] = null;
            return;
        }

        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    /**
     * Get the active mail configuration.
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }

    /**
     * Apply active settings to runtime mail configuration.
     */
    public static function applyActiveConfig(): void
    {
        $setting = static::getActive();
        if ($setting) {
            $setting->applyToConfig();
        }
    }

    /**
     * Apply this setting instance to Laravel runtime configuration.
     */
    public function applyToConfig(): void
    {
        if (! $this->is_active) {
            return;
        }

        $fromAddress = $this->from_address ?: config('mail.from.address', 'noreply@flowformhq.com');
        $fromName = $this->from_name ?: config('mail.from.name', 'FlowForm Engine');

        config([
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        switch ($this->driver) {
            case 'smtp':
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $this->host,
                    'mail.mailers.smtp.port' => $this->port ?: 587,
                    'mail.mailers.smtp.encryption' => $this->encryption ?: 'tls',
                    'mail.mailers.smtp.username' => $this->username,
                    'mail.mailers.smtp.password' => $this->password,
                ]);
                break;

            case 'ses':
                config([
                    'mail.default' => 'ses',
                    'services.ses.key' => $this->username,
                    'services.ses.secret' => $this->password,
                    'services.ses.region' => $this->region ?: 'us-east-1',
                ]);
                break;

            case 'resend':
                config([
                    'mail.default' => 'resend',
                    'resend.api_key' => $this->api_key,
                ]);
                break;

            case 'postmark':
                config([
                    'mail.default' => 'postmark',
                    'services.postmark.token' => $this->api_key,
                ]);
                break;

            case 'log':
                config(['mail.default' => 'log']);
                break;
        }
    }

    /**
     * Send a diagnostic test email and return success/error status.
     */
    public function sendTestEmail(string $recipient): array
    {
        $this->applyToConfig();

        try {
            Mail::raw(
                "Hello,\n\nThis is a diagnostic test email from FlowForm to verify your '{$this->driver}' mail transport configuration.\n\nTime: " . now()->toRfc2822String() . "\n\nFlowForm Team",
                function ($message) use ($recipient) {
                    $message->to($recipient)
                        ->subject("FlowForm SMTP Diagnostic Test - {$this->driver}");
                }
            );

            return [
                'success' => true,
                'message' => "Test email successfully dispatched to {$recipient} via {$this->driver}!",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Mail connection failed: {$e->getMessage()}",
            ];
        }
    }
}
