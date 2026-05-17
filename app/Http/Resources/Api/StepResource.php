<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_number' => $this->step_number,
            'title' => $this->title,
            'description' => $this->description,
            'is_visible' => $this->is_visible,
            'meta' => $this->meta,
            'fields' => FieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
