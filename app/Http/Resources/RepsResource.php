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
            'requested_area_names' => $this->requested_area_names ?? [],
            'work_areas' => $this->workAreas(),
            'work_lines' => $this->workLines(),
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

    private function workAreas()
    {
        $areaNames = collect($this->requested_area_names ?? []);

        if ($this->relationLoaded('areas')) {
            $areaNames = $areaNames->merge($this->areas->pluck('name'));
        }

        return $areaNames
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values();
    }

    private function workLines()
    {
        $lineNames = collect();

        if (!empty($this->requested_line_name)) {
            $lineNames->push($this->requested_line_name);
        }

        if ($this->relationLoaded('lines')) {
            $lineNames = $lineNames->merge($this->lines->pluck('name'));
        }

        return $lineNames
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values();
    }
}
