<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    /**
     * Disable updated_at since audit records are strictly immutable.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an immutable audit log entry (HIPAA / GDPR / SOC 2 compliance).
     */
    public static function record(
        string $action,
        ?Model $auditable = null,
        array $details = [],
        ?User $user = null,
        ?Request $request = null,
    ): self {
        $req = $request ?? request();

        return static::create([
            'user_id' => $user?->id ?? $req?->user()?->id,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            'details' => $details,
        ]);
    }
}
