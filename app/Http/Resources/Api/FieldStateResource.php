<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Services\FieldState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldStateResource extends JsonResource
{
    public function __construct(
        public readonly int $fieldId,
        public readonly string $fieldCode,
        public readonly FieldState $state,
    ) {
        parent::__construct($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field_id' => $this->fieldId,
            'field_code' => $this->fieldCode,
            'is_visible' => $this->state->isVisible,
            'is_required' => $this->state->isRequired,
        ];
    }
}
