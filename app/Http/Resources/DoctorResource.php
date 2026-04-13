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
    private const TIMES_BOOKED_FORWARD_DAYS = 30;

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
        $timesBookedAnchorDate = $targetDateObject->copy();
        $timesBookedWindowStart = $timesBookedAnchorDate->copy();
        $timesBookedWindowEnd = $timesBookedAnchorDate->copy()->addDays(self::TIMES_BOOKED_FORWARD_DAYS + 1)->startOfDay();
        $targetDateWindowStart = $targetDateObject->copy()->startOfDay();
        $targetDateWindowEnd = $targetDateWindowStart->copy()->addDay();
        $activeStatuses = ['pending', 'confirmed'];
        $candidateAppointmentsStartDate = $timesBookedWindowStart->copy()->subDay()->toDateString();
        $candidateAppointmentsEndDate = $timesBookedWindowEnd->toDateString();

        $candidateAppointments = Appointment::query()
            ->where('doctors_id', $this->id)
            ->whereIn('status', $activeStatuses)
            ->whereDate('date', '>=', $candidateAppointmentsStartDate)
            ->whereDate('date', '<', $candidateAppointmentsEndDate)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get(['date', 'start_time', 'end_time']);

        $bookedIntervalsForTargetDate = collect();
        $timesBookedEntries = collect();

        foreach ($candidateAppointments as $appointment) {
            $appointmentInterval = $this->buildAppointmentInterval($appointment);
            if ($appointmentInterval === null) {
                continue;
            }

            if ($this->intervalsOverlap(
                $appointmentInterval['start_at'],
                $appointmentInterval['end_at'],
                $timesBookedWindowStart,
                $timesBookedWindowEnd
            )) {
                $timesBookedEntries->push([
                    'appointment' => $appointment,
                    'start_at' => $appointmentInterval['start_at'],
                ]);
            }

            if ($this->intervalsOverlap(
                $appointmentInterval['start_at'],
                $appointmentInterval['end_at'],
                $targetDateWindowStart,
                $targetDateWindowEnd
            )) {
                $bookedIntervalsForTargetDate->push([
                    'start_at' => $appointmentInterval['start_at'],
                    'end_at' => $appointmentInterval['end_at'],
                ]);
            }
        }

        $timesBookedEntries = $timesBookedEntries
            ->sortBy(function (array $entry) {
                return $entry['start_at']->timestamp;
            })
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
            ->map(function ($availability) use ($targetDateObject, $bookedIntervalsForTargetDate) {
                $availabilityIntervals = $this->buildAvailabilityIntervalsForTargetDate($availability, $targetDateObject);
                $isBookedForDate = false;

                foreach ($availabilityIntervals as $availabilityInterval) {
                    if ($this->hasBookedOverlap(
                        $availabilityInterval['start_at'],
                        $availabilityInterval['end_at'],
                        $bookedIntervalsForTargetDate
                    )) {
                        $isBookedForDate = true;
                        break;
                    }
                }

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
            'times_booked' => $timesBookedEntries
                ->map(function (array $entry) {
                    $appointment = $entry['appointment'];

                    return [
                        'date' => $this->formatStoredDate((string) $appointment->getRawOriginal('date')),
                        'start_time' => $this->formatStoredTime((string) $appointment->getRawOriginal('start_time')),
                        'end_time' => $this->formatStoredTime((string) $appointment->getRawOriginal('end_time')),
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

    private function buildAppointmentInterval($appointment): ?array
    {
        $rawDate = trim((string) $appointment->getRawOriginal('date'));
        $appointmentDate = $this->parseStoredDate($rawDate);
        if ($appointmentDate === null) {
            return null;
        }

        $startAt = $this->buildDateTimeFromTime($appointmentDate, (string) $appointment->getRawOriginal('start_time'));
        $endAt = $this->buildDateTimeFromTime($appointmentDate, (string) $appointment->getRawOriginal('end_time'));
        if ($startAt === null || $endAt === null) {
            return null;
        }

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function buildAvailabilityIntervalsForTargetDate($availability, Carbon $targetDate): array
    {
        $intervals = [];
        $targetDateInterval = $this->buildAvailabilityIntervalForAnchorDate($availability, $targetDate);
        if ($targetDateInterval !== null) {
            $intervals[] = $targetDateInterval;
        }

        if ($this->isOvernightAvailability($availability)) {
            $previousDate = $targetDate->copy()->subDay();
            $previousDateInterval = $this->buildAvailabilityIntervalForAnchorDate($availability, $previousDate);
            if ($previousDateInterval !== null
                && $previousDateInterval['end_at']->greaterThan($targetDate->copy()->startOfDay())
            ) {
                $intervals[] = $previousDateInterval;
            }
        }

        return $intervals;
    }

    private function buildAvailabilityIntervalForAnchorDate($availability, Carbon $anchorDate): ?array
    {
        if (!$this->availabilityMatchesTargetDate((string) $availability->date, $anchorDate)) {
            return null;
        }

        $startAt = $this->buildDateTimeFromTime($anchorDate, (string) $availability->start_time);
        $endAt = $this->buildDateTimeFromTime($anchorDate, (string) $availability->end_time);
        if ($startAt === null || $endAt === null) {
            return null;
        }

        if ($this->isOvernightAvailability($availability)) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function isOvernightAvailability($availability): bool
    {
        if ((bool) $availability->ends_next_day) {
            return true;
        }

        $startTimeParts = $this->parseStoredTime((string) $availability->start_time);
        $endTimeParts = $this->parseStoredTime((string) $availability->end_time);
        if ($startTimeParts === null || $endTimeParts === null) {
            return false;
        }

        $startSeconds = ($startTimeParts[0] * 3600) + ($startTimeParts[1] * 60) + $startTimeParts[2];
        $endSeconds = ($endTimeParts[0] * 3600) + ($endTimeParts[1] * 60) + $endTimeParts[2];

        return $endSeconds <= $startSeconds;
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

            if ($this->intervalsOverlap($appointmentStartAt, $appointmentEndAt, $slotStartAt, $slotEndAt)) {
                return true;
            }
        }

        return false;
    }

    private function intervalsOverlap(
        Carbon $firstStartAt,
        Carbon $firstEndAt,
        Carbon $secondStartAt,
        Carbon $secondEndAt
    ): bool {
        return $firstStartAt->lessThan($secondEndAt) && $firstEndAt->greaterThan($secondStartAt);
    }

    private function formatStoredDate(string $date): string
    {
        $trimmedDate = trim($date);
        $parsedDate = $this->parseStoredDate($trimmedDate);
        if ($parsedDate !== null) {
            return $parsedDate->format(self::DATE_FORMAT);
        }

        return $trimmedDate;
    }

    private function formatStoredTime(string $time): string
    {
        $trimmedTime = trim($time);
        try {
            return Carbon::parse($trimmedTime)->format('h:i A');
        } catch (\Throwable $exception) {
            return $trimmedTime;
        }
    }

    private function parseStoredTime(string $time): ?array
    {
        $trimmedTime = trim($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    private function parseStoredDate(string $date): ?Carbon
    {
        $trimmedDate = trim($date);
        if ($trimmedDate === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmedDate, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
