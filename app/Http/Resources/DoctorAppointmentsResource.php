<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorAppointmentsResource extends JsonResource
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
            'doctor' => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'specialty' => [
                    'id' => $this->doctor->specialty->id,
                    'name' => $this->doctor->specialty->name,
                ],
            ],
            'representative' => [
                'id' => $this->representative->id,
                'name' => $this->representative->name,
            ],
            'phone' => $this->representative->phone,
            'date' => \Carbon\Carbon::parse($this->date)->format('Y-m-d'),
            'start_time' => \Carbon\Carbon::parse($this->start_time)->format('h:i A'),
            'end_time' => \Carbon\Carbon::parse($this->end_time)->format('h:i A'),
            'status' => $this->status,
            'appointment_code' => $this->appointment_code,
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ],
            'cancelled_by' => $this->cancelled_by,
        ];
    }
}
