<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'is_required' => $this->is_required,
            'is_repeatable' => $this->is_repeatable,
            'order' => $this->order,
            'field_type' => new FieldTypeResource($this->whenLoaded('fieldType')),
            'options' => FieldOptionResource::collection($this->whenLoaded('options')),
            'conditions' => ConditionResource::collection($this->whenLoaded('conditions')),
        ];
    }
}
