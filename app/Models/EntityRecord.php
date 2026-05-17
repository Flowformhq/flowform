<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityRecord extends Model
{
    protected $fillable = [
        'entity_id',
        'submission_id',
        'parent_id',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EntityRecord::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EntityRecord::class, 'parent_id');
    }

    public function submissionValues(): HasMany
    {
        return $this->hasMany(SubmissionValue::class);
    }
}
