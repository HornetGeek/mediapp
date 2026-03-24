<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockedRepsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'company' => $this->company->name ?? 'N/A',
            'date' => \Carbon\Carbon::parse($this->date)->format('l'),
            'start_time' => \Carbon\Carbon::parse($this->start_time)->format('h:i A'),
            'end_time' => \Carbon\Carbon::parse($this->end_time)->format('h:i A'),
        ];
    }
}
