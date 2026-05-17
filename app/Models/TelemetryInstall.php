<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $install_id
 * @property bool $opted_in
 * @property Carbon|null $last_ping_at
 */
class TelemetryInstall extends Model
{
    protected $table = 'telemetry_install';

    protected $fillable = [
        'install_id',
        'opted_in',
        'last_ping_at',
    ];

    protected function casts(): array
    {
        return [
            'install_id' => 'string',
            'opted_in' => 'boolean',
            'last_ping_at' => 'datetime',
        ];
    }

    public static function getOrCreate(): self
    {
        return static::firstOrCreate(
            [],
            ['install_id' => Str::uuid()->toString()]
        );
    }
}
