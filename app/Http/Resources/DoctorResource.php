<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Services\AvailabilityOccurrenceService;
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
        /** @var AvailabilityOccurrenceService $occurrenceService */
        $occurrenceService = app(AvailabilityOccurrenceService::class);
        $busyPeriod = $doctorBusyStatus->buildBusyPeriodPayload($this->resource);
        $explicitDate = $occurrenceService->parseExplicitRequestDate($request);
        $hasExplicitDate = $explicitDate !== null;
        $nowInCairo = Carbon::now(self::TIMEZONE);

        $bookedCountsByAvailabilityIdForExplicitDate = [];
        $bookedCountsByAvailabilityAndDate = [];

        if ($hasExplicitDate) {
            $bookedCountsByAvailabilityIdForExplicitDate = Appointment::query()
                ->where('doctors_id', $this->id)
                ->whereNotNull('doctor_availability_id')
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('date', $explicitDate)
                ->selectRaw('doctor_availability_id, COUNT(*) as booked_reps_count')
                ->groupBy('doctor_availability_id')
                ->pluck('booked_reps_count', 'doctor_availability_id')
                ->mapWithKeys(function ($count, $availabilityId) {
                    return [(int) $availabilityId => (int) $count];
                })
                ->all();
        } else {
            $bookedCountsByAvailabilityAndDate = $occurrenceService->loadBookedCountsByAvailabilityAndDate(
                (int) $this->id,
                $nowInCairo->toDateString(),
                $occurrenceService->bookingCountHorizonEnd($nowInCairo)
            );
        }

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
            ->map(function ($availability) use (
                $hasExplicitDate,
                $explicitDate,
                $occurrenceService,
                $nowInCairo,
                $bookedCountsByAvailabilityIdForExplicitDate,
                $bookedCountsByAvailabilityAndDate
            ) {
                if ($hasExplicitDate) {
                    $countedForDate = $explicitDate;
                    $targetDateObject = Carbon::createFromFormat(self::DATE_FORMAT, $explicitDate, self::TIMEZONE)->startOfDay();
                    $bookedRepsCount = $this->countBookedRepsForExplicitDate(
                        $availability,
                        $targetDateObject,
                        $bookedCountsByAvailabilityIdForExplicitDate,
                        $occurrenceService
                    );
                } else {
                    $countedForDate = $occurrenceService->resolveNextOccurrenceDate($availability, $nowInCairo);
                    $bookedRepsCount = 0;
                    if ($countedForDate !== null) {
                        $bookedRepsCount = (int) (
                            $bookedCountsByAvailabilityAndDate[(int) $availability->id][$countedForDate] ?? 0
                        );
                    }
                }

                $maxRepsPerRange = $availability->max_reps_per_range === null
                    ? null
                    : max(1, (int) $availability->max_reps_per_range);
                $remainingRepsCount = $maxRepsPerRange === null
                    ? null
                    : max(0, $maxRepsPerRange - $bookedRepsCount);

                $availability->setAttribute('booked_reps_count', $bookedRepsCount);
                $availability->setAttribute('remaining_reps_count', $remainingRepsCount);
                $availability->setAttribute('counted_for_date', $countedForDate);
                $availability->setAttribute(
                    'is_booked_for_date',
                    $countedForDate !== null
                        && $maxRepsPerRange !== null
                        && $bookedRepsCount >= $maxRepsPerRange
                );

                return $availability;
            })
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'specialty' => $this->specialty->name ?? null,
            'address_1' => $this->address_1,
            'booked_for_date' => $hasExplicitDate ? $explicitDate : null,
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

    private function countBookedRepsForExplicitDate(
        $availability,
        Carbon $targetDate,
        array $bookedCountsByAvailabilityId,
        AvailabilityOccurrenceService $occurrenceService
    ): int {
        if (!$occurrenceService->availabilityMatchesOccurrenceDate((string) $availability->date, $targetDate)) {
            return 0;
        }

        return (int) ($bookedCountsByAvailabilityId[(int) $availability->id] ?? 0);
    }
}
