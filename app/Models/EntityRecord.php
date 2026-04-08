<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityRecord extends Model
{
    protected $fillable = [
        'entity_id',
        'submission_id',
        'parent_id',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function parent()
    {
        return $this->belongsTo(EntityRecord::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(EntityRecord::class, 'parent_id');
    }

    public function submissionValues()
    {
        return $this->hasMany(SubmissionValue::class);
    }
}
