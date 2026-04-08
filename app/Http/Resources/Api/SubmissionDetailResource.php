<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Build field_code => value map from loaded values
        $values = $this->values->mapWithKeys(function ($submissionValue) {
            return [$submissionValue->field->code => $submissionValue->value];
        });

        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress_percentage' => $this->progressPercentage(),
            'meta' => $this->meta,
            'values' => $values,
            'created_at' => $this->created_at,
        ];
    }
}
