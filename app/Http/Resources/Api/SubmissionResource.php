<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin Submission */
class SubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $includes = array_filter(array_map('trim', explode(',', (string) $request->query('include', ''))));
        $includeMeta = in_array('meta', $includes, true);

        $meta = is_array($this->meta)
            ? ($includeMeta ? $this->meta : Arr::except($this->meta, ['ip']))
            : $this->meta;

        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress_percentage' => $this->progressPercentage(),
            'data' => $this->getDataWithSignedUrls(),
            'meta' => $meta,
            'created_at' => $this->created_at,
        ];
    }
}
