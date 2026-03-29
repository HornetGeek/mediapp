<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
            'subscription_start' => $this->subscription_start ? Carbon::parse($this->subscription_start)->format('Y-m-d') : null,
            'subscription_end' => $this->subscription_end ? Carbon::parse($this->subscription_end)->format('Y-m-d') : null,
            'subscription_plan' => $this->package?->name,
            'plan_type' => $this->package?->resolvePlanType(),
            'billing_months' => $this->package?->resolveBillingMonths(),
            'duration' => $this->package?->duration,
            'Subscription Plan' => $this->package?->name,
            'visits_per_day' => $this->visits_per_day,
            'num_of_reps' => $this->num_of_reps,
            'status' => $this->status,
            
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
