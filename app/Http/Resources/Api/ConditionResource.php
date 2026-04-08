<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'depends_on_field_code' => $this->dependsOnField?->code,
            'operator' => $this->operator,
            'value' => $this->value,
            'action' => $this->action,
        ];
    }
}
