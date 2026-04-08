<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormSchemaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'steps' => StepResource::collection($this->whenLoaded('steps')),
            'entities' => EntityResource::collection($this->whenLoaded('entities')),
        ];
    }
}
