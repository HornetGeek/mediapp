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
        $targetDateObject = Carbon::createFromFormat(self::DATE_FORMAT, $targetDate, self::TIMEZONE);
        $activeStatuses = ['pending', 'confirmed'];

        $bookedAppointments = Appointment::query()
            ->where('doctors_id', $this->id)
            ->whereDate('date', $targetDate)
            ->whereIn('status', $activeStatuses)
            ->orderBy('start_time')
            ->get(['date', 'start_time', 'end_time']);

        $bookedIntervals = $bookedAppointments
            ->map(function ($appointment) use ($targetDateObject) {
                $startAt = $this->buildDateTimeFromTime($targetDateObject, (string) $appointment->getRawOriginal('start_time'));
                $endAt = $this->buildDateTimeFromTime($targetDateObject, (string) $appointment->getRawOriginal('end_time'));

                if ($startAt === null || $endAt === null) {
                    return null;
                }

                return [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ];
            })
            ->filter()
            ->values();

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
            ->map(function ($availability) use ($targetDateObject, $bookedIntervals) {
                $availabilityInterval = $this->buildAvailabilityIntervalForDate($availability, $targetDateObject);
                $isBookedForDate = $availabilityInterval !== null
                    && $this->hasBookedOverlap($availabilityInterval['start_at'], $availabilityInterval['end_at'], $bookedIntervals);

                $availability->setAttribute('is_booked_for_date', $isBookedForDate);

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
            'times_booked' => $bookedAppointments
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

    private function buildDateTimeFromTime(Carbon $date, string $time): ?Carbon
    {
        $timeParts = $this->parseStoredTime($time);
        if ($timeParts === null) {
            return null;
        }

        return $date->copy()->setTime($timeParts[0], $timeParts[1], $timeParts[2]);
    }

    private function buildAvailabilityIntervalForDate($availability, Carbon $targetDate): ?array
    {
        if (!$this->availabilityMatchesTargetDate((string) $availability->date, $targetDate)) {
            return null;
        }

        $startAt = $this->buildDateTimeFromTime($targetDate, (string) $availability->start_time);
        $endAt = $this->buildDateTimeFromTime($targetDate, (string) $availability->end_time);
        if ($startAt === null || $endAt === null) {
            return null;
        }

        $isOvernight = (bool) $availability->ends_next_day
            || ((string) $availability->end_time <= (string) $availability->start_time);
        if ($isOvernight) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
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

    private function hasBookedOverlap(Carbon $slotStartAt, Carbon $slotEndAt, $bookedIntervals): bool
    {
        foreach ($bookedIntervals as $interval) {
            $appointmentStartAt = $interval['start_at'];
            $appointmentEndAt = $interval['end_at'];

            $hasOverlap = (
                $appointmentStartAt->lessThan($slotEndAt)
                && $appointmentEndAt->greaterThan($slotStartAt)
            ) || $appointmentStartAt->equalTo($slotStartAt);

            if ($hasOverlap) {
                return true;
            }
        }

        return false;
    }

    private function parseStoredTime(string $time): ?array
    {
        $trimmedTime = trim($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }
}
