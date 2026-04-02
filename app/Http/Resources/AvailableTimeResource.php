<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableTimeResource extends JsonResource
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
            'date' => $this->date,
            'start_time' => Carbon::parse($this->start_time)->format('h:i A'), // 12h format
            'end_time' => Carbon::parse($this->end_time)->format('h:i A'),
            'ends_next_day' => (bool) $this->ends_next_day,
            'status' => $this->status,
        ];
    }
}
