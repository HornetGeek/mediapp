<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiltersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->representative->id,
            'representative' => $this->representative->name,
            'date' => \Carbon\Carbon::parse($this->date)->format('Y-m-d'),//('l')
            'start_time' => \Carbon\Carbon::parse($this->start_time)->format('h:i A'),
            'end_time' => \Carbon\Carbon::parse($this->end_time)->format('h:i A'),
            'status' => $this->status,
            'appointment_code' => $this->appointment_code,
            'company' => $this->company->name,
        ];
    }
}
