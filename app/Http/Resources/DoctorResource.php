<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Services\DoctorBusyStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    private const TIMEZONE = 'Africa/Cairo';
    private const DATE_FORMAT = 'Y-m-d';

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
        $targetDate = $this->resolveTargetDate($request);
        $targetDateObject = Carbon::createFromFormat(self::DATE_FORMAT, $targetDate, self::TIMEZONE)->startOfDay();
        $activeStatuses = ['pending', 'confirmed'];

        $bookedCountsByAvailabilityId = Appointment::query()
            ->where('doctors_id', $this->id)
            ->whereNotNull('doctor_availability_id')
            ->whereIn('status', $activeStatuses)
            ->whereDate('date', $targetDate)
            ->selectRaw('doctor_availability_id, COUNT(*) as booked_reps_count')
            ->groupBy('doctor_availability_id')
            ->pluck('booked_reps_count', 'doctor_availability_id')
            ->mapWithKeys(function ($count, $availabilityId) {
                return [(int) $availabilityId => (int) $count];
            });

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
            ->map(function ($availability) use ($targetDateObject, $bookedCountsByAvailabilityId) {
                $bookedRepsCount = $this->countBookedRepsForAvailabilityDate(
                    $availability,
                    $targetDateObject,
                    $bookedCountsByAvailabilityId
                );
                $maxRepsPerRange = $availability->max_reps_per_range === null
                    ? null
                    : max(1, (int) $availability->max_reps_per_range);
                $remainingRepsCount = $maxRepsPerRange === null
                    ? null
                    : max(0, $maxRepsPerRange - $bookedRepsCount);

                $availability->setAttribute('booked_reps_count', $bookedRepsCount);
                $availability->setAttribute('remaining_reps_count', $remainingRepsCount);
                $availability->setAttribute(
                    'is_booked_for_date',
                    $maxRepsPerRange !== null && $bookedRepsCount >= $maxRepsPerRange
                );

                return $availability;
            })
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialty' => $this->specialty->name ?? 'N/A',
            'address_1' => $this->address_1,
            'booked_for_date' => $targetDate,
            'available_times' => AppAvailableTimeResource::collection($availableTimes),
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

    private function resolveTargetDate(Request $request): string
    {
        $dateInput = trim((string) $request->query('date'));

        if ($dateInput !== '') {
            try {
                $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $dateInput, self::TIMEZONE);
                if ($parsedDate->format(self::DATE_FORMAT) === $dateInput) {
                    return $parsedDate->format(self::DATE_FORMAT);
                }
            } catch (\Throwable $exception) {
                // Controller validation should reject invalid values; keep a safe fallback.
            }
        }

        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    private function countBookedRepsForAvailabilityDate($availability, Carbon $targetDate, $bookedCountsByAvailabilityId): int
    {
        if (!$this->availabilityMatchesTargetDate((string) $availability->date, $targetDate)) {
            return 0;
        }

        return (int) ($bookedCountsByAvailabilityId[(int) $availability->id] ?? 0);
    }

    private function availabilityMatchesTargetDate(string $availabilityDate, Carbon $targetDate): bool
    {
        $trimmedAvailabilityDate = trim($availabilityDate);
        if ($trimmedAvailabilityDate === '') {
            return false;
        }

        if (strtolower($trimmedAvailabilityDate) === strtolower($targetDate->format('l'))) {
            return true;
        }

        try {
            $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $trimmedAvailabilityDate, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return false;
        }

        if ($parsedDate->format(self::DATE_FORMAT) !== $trimmedAvailabilityDate) {
            return false;
        }

        return $parsedDate->toDateString() === $targetDate->toDateString();
    }

}
