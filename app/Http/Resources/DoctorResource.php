<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
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
            'specialty' => $this->specialty->name ?? 'N/A',
            'address_1' => $this->address_1,
            'available_times' => AvailableTimeResource::collection($this->availableTimes),
            'times_booked' => Appointment::where('doctors_id', $this->id)
                ->where('status', 'pending')
                ->orderBy('date')
                ->get()
                ->map(function ($appointment) {
                    return [
                        'date' => Carbon::parse($appointment->date)->format('Y-m-d'),
                        'start_time' => Carbon::parse($appointment->start_time)->format('h:i A'),
                        'end_time' => Carbon::parse($appointment->end_time)->format('h:i A'),
                    ];
                }),
            'is_fav' => $this->favoredByReps->isNotEmpty() ? (bool) $this->favoredByReps->first()->pivot->is_fav : false,
            'status' => $this->status,
            'from_date' => $this->from_date ? Carbon::parse($this->from_date)->format('l'): null,
            'to_date' => $this->to_date ? Carbon::parse($this->to_date)->format('l'): null,
        ];
    }
}
