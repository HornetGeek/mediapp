<?php

namespace App\Services;

use App\Models\DoctorAvailability;
use App\Models\Doctors;
use Carbon\Carbon;

class DoctorAvailabilityCreationService
{
    public function prepareAvailabilityPayload(Doctors $doctor, array $validated): array
    {
        $normalizedDates = $this->normalizeAvailabilityDates($validated['date'] ?? null);
        if (isset($normalizedDates['error'])) {
            return ['error' => $normalizedDates['error']];
        }

        $endsNextDay = (bool) ($validated['ends_next_day'] ?? false);
        $normalizedTimes = $this->normalizeAvailabilityTimes(
            (string) ($validated['start_time'] ?? ''),
            (string) ($validated['end_time'] ?? ''),
            $endsNextDay
        );
        if (isset($normalizedTimes['error'])) {
            return ['error' => $normalizedTimes['error']];
        }

        if ($this->hasRequestedAvailabilityOverlap(
            $normalizedDates['dates'],
            $normalizedTimes['start_time'],
            $normalizedTimes['end_time'],
            $normalizedTimes['ends_next_day']
        )) {
            return ['error' => 'This time conflicts with an existing availability'];
        }

        foreach ($normalizedDates['dates'] as $normalizedDate) {
            if ($this->hasAvailabilityOverlap(
                (int) $doctor->id,
                $normalizedDate,
                $normalizedTimes['start_time'],
                $normalizedTimes['end_time'],
                $normalizedTimes['ends_next_day']
            )) {
                return ['error' => 'This time conflicts with an existing availability'];
            }
        }

        return [
            'dates' => $normalizedDates['dates'],
            'start_time' => $normalizedTimes['start_time'],
            'end_time' => $normalizedTimes['end_time'],
            'ends_next_day' => $normalizedTimes['ends_next_day'],
            'max_reps_per_range' => isset($validated['max_reps_per_range'])
                ? (int) $validated['max_reps_per_range']
                : null,
            'visit_time_type' => $validated['visit_time_type'] ?? 'between',
        ];
    }

    public function prepareAvailabilityPayloads(Doctors $doctor, array $availabilityInputs): array
    {
        $availabilityPayloads = [];
        foreach ($availabilityInputs as $availabilityInput) {
            $availabilityPayload = $this->prepareAvailabilityPayload($doctor, $availabilityInput);
            if (isset($availabilityPayload['error'])) {
                return ['error' => $availabilityPayload['error']];
            }

            $availabilityPayloads[] = $availabilityPayload;
        }

        if ($this->hasPreparedAvailabilityOverlap($availabilityPayloads)) {
            return ['error' => 'This time conflicts with an existing availability'];
        }

        return ['payloads' => $availabilityPayloads];
    }

    public function createAvailabilityRows(Doctors $doctor, array $availabilityPayload): void
    {
        foreach ($availabilityPayload['dates'] as $normalizedDate) {
            $doctor->availableTimes()->create([
                'date' => $normalizedDate,
                'start_time' => $availabilityPayload['start_time'],
                'end_time' => $availabilityPayload['end_time'],
                'ends_next_day' => $availabilityPayload['ends_next_day'],
                'max_reps_per_range' => $availabilityPayload['max_reps_per_range'],
                'visit_time_type' => $availabilityPayload['visit_time_type'],
                'status' => 'available',
            ]);
        }
    }

    private function normalizeAvailabilityTimes(string $startTime, string $endTime, bool $endsNextDay): array
    {
        $normalizedStartTime = $this->normalizeAvailabilityTime($startTime);
        $normalizedEndTime = $this->normalizeAvailabilityTime(
            $this->normalizeEndTimeBoundary((string) $endTime)
        );

        if ($normalizedStartTime === null || $normalizedEndTime === null) {
            return ['error' => 'Invalid time format, please use hh:mm AM/PM or HH:mm'];
        }

        if ($normalizedStartTime === $normalizedEndTime) {
            return ['error' => 'Start time must be before end time'];
        }

        if (!$endsNextDay && $normalizedStartTime > $normalizedEndTime) {
            return ['error' => 'Start time must be before end time'];
        }

        if ($endsNextDay && $normalizedEndTime > $normalizedStartTime) {
            $endsNextDay = false;
        }

        return [
            'start_time' => $normalizedStartTime,
            'end_time' => $normalizedEndTime,
            'ends_next_day' => $endsNextDay,
        ];
    }

    private function hasPreparedAvailabilityOverlap(array $availabilityPayloads): bool
    {
        $intervals = [];
        foreach ($availabilityPayloads as $availabilityPayload) {
            foreach ($availabilityPayload['dates'] as $date) {
                $targetWeekday = $this->normalizeStoredAvailabilityWeekday($date);
                if ($targetWeekday === null) {
                    continue;
                }

                $intervals[] = $this->buildWeekdayInterval(
                    $targetWeekday,
                    $availabilityPayload['start_time'],
                    $availabilityPayload['end_time'],
                    $availabilityPayload['ends_next_day']
                );
            }
        }

        $intervalCount = count($intervals);
        for ($leftIndex = 0; $leftIndex < $intervalCount; $leftIndex++) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < $intervalCount; $rightIndex++) {
                if ($this->recurringIntervalsOverlap(
                    $intervals[$leftIndex]['start_at'],
                    $intervals[$leftIndex]['end_at'],
                    $intervals[$rightIndex]['start_at'],
                    $intervals[$rightIndex]['end_at']
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeEndTimeBoundary(string $endTime): string
    {
        $trimmedEndTime = trim($endTime);

        if (preg_match('/^24:00(?::00)?$/', $trimmedEndTime) === 1) {
            return '23:59:00';
        }

        return $trimmedEndTime;
    }

    private function normalizeAvailabilityDate(string $date): array
    {
        $normalizedWeekday = $this->normalizeStoredAvailabilityWeekday($date);
        if ($normalizedWeekday === null) {
            return ['error' => 'Invalid date format, please use weekday name or YYYY-MM-DD'];
        }

        return ['date' => $normalizedWeekday];
    }

    private function normalizeAvailabilityDates($date): array
    {
        $dates = is_array($date) ? array_values($date) : [$date];
        if (count($dates) === 0) {
            return ['error' => 'The date field is required.'];
        }

        $normalizedDates = [];
        foreach ($dates as $dateItem) {
            if (!is_string($dateItem)) {
                return ['error' => 'Invalid date format, please use weekday name or YYYY-MM-DD'];
            }

            $normalizedDate = $this->normalizeAvailabilityDate($dateItem);
            if (isset($normalizedDate['error'])) {
                return $normalizedDate;
            }

            if (in_array($normalizedDate['date'], $normalizedDates, true)) {
                return ['error' => 'Duplicate availability date in request'];
            }

            $normalizedDates[] = $normalizedDate['date'];
        }

        return ['dates' => $normalizedDates];
    }

    private function normalizeAvailabilityTime(string $time): ?string
    {
        $trimmedTime = trim($time);

        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*([AaPp][Mm])$/', $trimmedTime, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $meridiem = strtoupper($matches[3]);

            if ($meridiem === 'AM' && $hour === 12) {
                $hour = 0;
            } elseif ($meridiem === 'PM' && $hour !== 12) {
                $hour += 12;
            }

            return sprintf('%02d:%02d:00', $hour, $minute);
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $trimmedTime, $matches) === 1) {
            return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function hasAvailabilityOverlap(
        int $doctorId,
        string $date,
        string $startTime,
        string $endTime,
        bool $endsNextDay
    ): bool {
        $targetWeekday = $this->normalizeStoredAvailabilityWeekday($date);
        if ($targetWeekday === null) {
            return false;
        }

        $targetInterval = $this->buildWeekdayInterval($targetWeekday, $startTime, $endTime, $endsNextDay);

        $candidateAvailabilities = DoctorAvailability::query()
            ->where('doctors_id', $doctorId)
            ->where('status', 'available')
            ->get(['id', 'date', 'start_time', 'end_time', 'ends_next_day']);

        foreach ($candidateAvailabilities as $availability) {
            $existingWeekday = $this->normalizeStoredAvailabilityWeekday((string) $availability->date);
            if ($existingWeekday === null) {
                continue;
            }

            $existingEndsNextDay = (bool) $availability->ends_next_day
                || $this->isLegacyOvernightInterval((string) $availability->start_time, (string) $availability->end_time);

            $existingInterval = $this->buildWeekdayInterval(
                $existingWeekday,
                (string) $availability->start_time,
                (string) $availability->end_time,
                $existingEndsNextDay
            );

            if ($this->recurringIntervalsOverlap(
                $targetInterval['start_at'],
                $targetInterval['end_at'],
                $existingInterval['start_at'],
                $existingInterval['end_at']
            )) {
                return true;
            }
        }

        return false;
    }

    private function hasRequestedAvailabilityOverlap(
        array $dates,
        string $startTime,
        string $endTime,
        bool $endsNextDay
    ): bool {
        $requestedIntervals = [];

        foreach ($dates as $date) {
            $targetWeekday = $this->normalizeStoredAvailabilityWeekday($date);
            if ($targetWeekday === null) {
                continue;
            }

            $requestedIntervals[] = $this->buildWeekdayInterval($targetWeekday, $startTime, $endTime, $endsNextDay);
        }

        $intervalCount = count($requestedIntervals);
        for ($leftIndex = 0; $leftIndex < $intervalCount; $leftIndex++) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < $intervalCount; $rightIndex++) {
                if ($this->recurringIntervalsOverlap(
                    $requestedIntervals[$leftIndex]['start_at'],
                    $requestedIntervals[$leftIndex]['end_at'],
                    $requestedIntervals[$rightIndex]['start_at'],
                    $requestedIntervals[$rightIndex]['end_at']
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildWeekdayInterval(string $weekday, string $startTime, string $endTime, bool $endsNextDay): array
    {
        $weekdayMap = $this->weekdayToIndexMap();
        $weekdayIndex = $weekdayMap[$weekday] ?? 0;
        $anchorSunday = Carbon::create(2026, 1, 4, 0, 0, 0);

        [$startHour, $startMinute, $startSecond] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute, $endSecond] = array_map('intval', explode(':', $endTime));

        $startAt = $anchorSunday->copy()->addDays($weekdayIndex)->setTime($startHour, $startMinute, $startSecond);
        $endAt = $anchorSunday->copy()->addDays($weekdayIndex)->setTime($endHour, $endMinute, $endSecond);

        if ($endsNextDay) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function intervalsOverlap(Carbon $leftStartAt, Carbon $leftEndAt, Carbon $rightStartAt, Carbon $rightEndAt): bool
    {
        return $leftStartAt->lt($rightEndAt) && $leftEndAt->gt($rightStartAt);
    }

    private function recurringIntervalsOverlap(Carbon $leftStartAt, Carbon $leftEndAt, Carbon $rightStartAt, Carbon $rightEndAt): bool
    {
        foreach ([-7, 0, 7] as $shiftDays) {
            if ($this->intervalsOverlap(
                $leftStartAt,
                $leftEndAt,
                $rightStartAt->copy()->addDays($shiftDays),
                $rightEndAt->copy()->addDays($shiftDays)
            )) {
                return true;
            }
        }

        return false;
    }

    private function normalizeStoredAvailabilityWeekday(string $date): ?string
    {
        $trimmedDate = trim($date);
        if ($trimmedDate === '') {
            return null;
        }

        $normalizedWeekday = strtolower($trimmedDate);
        if (array_key_exists($normalizedWeekday, $this->weekdayToIndexMap())) {
            return $normalizedWeekday;
        }

        try {
            $normalizedDate = Carbon::createFromFormat('Y-m-d', $trimmedDate);
        } catch (\Exception $exception) {
            return null;
        }

        if ($normalizedDate->format('Y-m-d') !== $trimmedDate) {
            return null;
        }

        return strtolower($normalizedDate->format('l'));
    }

    private function weekdayToIndexMap(): array
    {
        return [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
    }

    private function isLegacyOvernightInterval(string $startTime, string $endTime): bool
    {
        return $endTime <= $startTime;
    }
}
