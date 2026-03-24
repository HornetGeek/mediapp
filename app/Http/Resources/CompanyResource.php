<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            // 'address' => $this->address,
            'subscription_start' => \Carbon\Carbon::parse($this->subscription_start)->format('Y-m-d'),
            'subscription_end' => \Carbon\Carbon::parse($this->subscription_end)->format('Y-m-d'),
            'Subscription Plan' => $this->package->name,
            'visits_per_day' => $this->visits_per_day,
            'num_of_reps' => $this->num_of_reps,
            'status' => $this->status,
            
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
