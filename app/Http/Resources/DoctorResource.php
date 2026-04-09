<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Services\DoctorBusyStatusService;
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
        /** @var DoctorBusyStatusService $doctorBusyStatus */
        $doctorBusyStatus = app(DoctorBusyStatusService::class);
        $busyPeriod = $doctorBusyStatus->buildBusyPeriodPayload($this->resource);

        $availableTimes = $this->relationLoaded('availableTimes')
            ? $this->availableTimes
            : $this->availableTimes()->where('status', 'available')->get();

        $availableTimes = $availableTimes
            ->where('status', 'available')
            ->sortBy(function ($availability) {
                return sprintf(
                    '%02d|%s|%010d',
                    $this->weekdaySortIndex((string) $availability->date),
                    $this->normalizeSortableTime((string) $availability->start_time),
                    (int) $availability->id
                );
            })
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialty' => $this->specialty->name ?? 'N/A',
            'address_1' => $this->address_1,
            'available_times' => AppAvailableTimeResource::collection($availableTimes),
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
            'from_date' => $doctorBusyStatus->formatDateForResponse((string) $this->from_date),
            'to_date' => $doctorBusyStatus->formatDateForResponse((string) $this->to_date),
            'busy_period' => $busyPeriod,
        ];
    }

    private function weekdaySortIndex(string $value): int
    {
        $normalizedWeekday = $this->normalizeWeekday($value);

        $weekdayOrder = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        return $weekdayOrder[$normalizedWeekday] ?? 7;
    }

    private function normalizeWeekday(string $value): ?string
    {
        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return null;
        }

        $weekday = strtolower($trimmedValue);
        $weekdayOrder = [
            'sunday' => true,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
        ];

        if (isset($weekdayOrder[$weekday])) {
            return $weekday;
        }

        try {
            $dateObject = Carbon::createFromFormat('Y-m-d', $trimmedValue);
        } catch (\Exception $exception) {
            return null;
        }

        if ($dateObject->format('Y-m-d') !== $trimmedValue) {
            return null;
        }

        return strtolower($dateObject->format('l'));
    }

    private function normalizeSortableTime(string $value): string
    {
        $trimmedValue = trim($value);

        try {
            return Carbon::parse($trimmedValue)->format('H:i:s');
        } catch (\Exception $exception) {
            return '99:99:99';
        }
    }
}
