<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->blockable_type),

            'blockable' => [
                'id' => $this->blockable->id ?? null,
                'name' => $this->blockable->name ?? null,
            ],
        ];
    }
}
