<?php

namespace App\Http\Resources;

use App\Support\CompanyPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'requested_line_name' => $this->requested_line_name,
            'status' => $this->status,
            'registration_status' => $this->registration_status ?? 'active',
            'can_book' => ($this->registration_status ?? 'active') === 'active',
            'requires_company_approval' => ($this->registration_status ?? 'active') === 'pending',
            'daily_visits_limit' => max(0, (int) ($this->daily_visits_limit ?? (optional($this->company)->visits_per_day ?? 0))),
            'used_visits_today' => max(0, (int) ($this->used_visits_today ?? 0)),
            'remaining_visits_today' => max(0, (int) ($this->remaining_visits_today ?? 0)),
            'areas' => $this->whenLoaded('areas', function () {
                return $this->areas->map(function ($area) {
                    return [
                        'id' => $area->id,
                        'name' => $area->name,
                    ];
                });
            }),
            'lines' => $this->whenLoaded('lines', function () {
                return $this->lines->map(function ($line){
                    return [
                        'id' => $line->id,
                        'name' => $line->name,
                    ];
                });
            }),
            'company' => CompanyPayload::forRepresentative($this->resource),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
