<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress_percentage' => $this->progressPercentage(),
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
