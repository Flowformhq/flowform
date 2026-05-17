<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionValue extends Model
{
    protected $fillable = [
        'submission_id',
        'field_id',
        'entity_record_id',
        'value',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function entityRecord(): BelongsTo
    {
        return $this->belongsTo(EntityRecord::class);
    }
}
