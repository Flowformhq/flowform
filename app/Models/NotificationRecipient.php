<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\VerifyNotificationRecipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class NotificationRecipient extends Model
{
    protected $fillable = [
        'form_id',
        'email',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markAsVerified(): bool
    {
        return $this->update([
            'verified_at' => now(),
        ]);
    }

    public function resendVerification(): bool
    {
        if ($this->isVerified()) {
            return false;
        }

        $key10m = "resend_verification_10m:{$this->id}";
        $keyDaily = "resend_verification_daily:{$this->id}";

        if (RateLimiter::tooManyAttempts($key10m, 1) || RateLimiter::tooManyAttempts($keyDaily, 5)) {
            return false;
        }

        RateLimiter::hit($key10m, 600);
        RateLimiter::hit($keyDaily, 86400);

        Mail::to($this->email)->send(new VerifyNotificationRecipient($this));

        return true;
    }
}
