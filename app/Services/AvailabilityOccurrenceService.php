<?php

namespace App\Services;

use App\Models\DoctorAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AvailabilityOccurrenceService
{
    public const TIMEZONE = 'Africa/Cairo';
    private const DATE_FORMAT = 'Y-m-d';
    private const BOOKING_COUNT_HORIZON_DAYS = 60;

    public function parseExplicitRequestDate(Request $request): ?string
    {
        $dateInput = trim((string) $request->query('date'));
        if ($dateInput === '') {
            return null;
        }

        try {
            $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $dateInput, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($parsedDate->format(self::DATE_FORMAT) !== $dateInput) {
            return null;
        }

        return $parsedDate->format(self::DATE_FORMAT);
    }

    public function bookingCountHorizonEnd(?Carbon $now = null): string
    {
        $anchor = ($now ?? Carbon::now(self::TIMEZONE))->copy()->startOfDay();

        return $anchor->copy()->addDays(self::BOOKING_COUNT_HORIZON_DAYS)->toDateString();
    }

    /**
     * @return array<int, array<string, int>> availability_id => [Y-m-d => count]
     */
    public function loadBookedCountsByAvailabilityAndDate(
        int $doctorId,
        string $fromDate,
        string $toDate
    ): array {
        $rows = \App\Models\Appointment::query()
            ->where('doctors_id', $doctorId)
            ->whereNotNull('doctor_availability_id')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->selectRaw('doctor_availability_id, date, COUNT(*) as booked_reps_count')
            ->groupBy('doctor_availability_id', 'date')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $availabilityId = (int) $row->doctor_availability_id;
            $occurrenceDate = Carbon::parse((string) $row->date, self::TIMEZONE)->toDateString();
            $counts[$availabilityId][$occurrenceDate] = (int) $row->booked_reps_count;
        }

        return $counts;
    }

    public function resolveNextOccurrenceDate(DoctorAvailability $availability, ?Carbon $now = null): ?string
    {
        $now = ($now ?? Carbon::now(self::TIMEZONE))->copy()->setTimezone(self::TIMEZONE);
        $availabilityDate = trim((string) $availability->date);
        if ($availabilityDate === '') {
            return null;
        }

        $fixedDate = $this->parseFixedCalendarDate($availabilityDate);
        if ($fixedDate !== null) {
            if ($fixedDate->copy()->startOfDay()->lessThan($now->copy()->startOfDay())) {
                return null;
            }

            return $fixedDate->toDateString();
        }

        $weekday = $this->normalizeWeekdayName($availabilityDate);
        if ($weekday === null) {
            return null;
        }

        $candidate = $now->copy()->startOfDay();
        for ($dayOffset = 0; $dayOffset < 8; $dayOffset++) {
            if (strtolower($candidate->format('l')) !== $weekday) {
                $candidate->addDay();
                continue;
            }

            $interval = $this->buildAvailabilityInterval($availability, $candidate);
            if ($interval !== null && $interval['end_at']->greaterThan($now)) {
                return $candidate->toDateString();
            }

            $candidate->addDay();
        }

        return null;
    }

    public function availabilityMatchesOccurrenceDate(string $availabilityDate, Carbon $occurrenceDate): bool
    {
        $trimmedAvailabilityDate = trim($availabilityDate);
        if ($trimmedAvailabilityDate === '') {
            return false;
        }

        if (strtolower($trimmedAvailabilityDate) === strtolower($occurrenceDate->format('l'))) {
            return true;
        }

        $fixedDate = $this->parseFixedCalendarDate($trimmedAvailabilityDate);

        return $fixedDate !== null
            && $fixedDate->toDateString() === $occurrenceDate->toDateString();
    }

    /**
     * @return array{start_at: Carbon, end_at: Carbon}|null
     */
    public function buildAvailabilityInterval(DoctorAvailability $availability, Carbon $anchorDate): ?array
    {
        $startTimeParts = $this->parseStoredTime((string) $availability->start_time);
        $endTimeParts = $this->parseStoredTime((string) $availability->end_time);
        if ($startTimeParts === null || $endTimeParts === null) {
            return null;
        }

        $startAt = $anchorDate->copy()->setTime($startTimeParts[0], $startTimeParts[1], $startTimeParts[2]);
        $endAt = $anchorDate->copy()->setTime($endTimeParts[0], $endTimeParts[1], $endTimeParts[2]);

        if ($this->isOvernightAvailability($availability)) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function isOvernightAvailability(DoctorAvailability $availability): bool
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

    private function parseFixedCalendarDate(string $value): ?Carbon
    {
        try {
            $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $value, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($parsedDate->format(self::DATE_FORMAT) !== $value) {
            return null;
        }

        return $parsedDate->startOfDay();
    }

    private function normalizeWeekdayName(string $value): ?string
    {
        $weekday = strtolower(trim($value));
        $weekdays = [
            'sunday' => true,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
        ];

        return isset($weekdays[$weekday]) ? $weekday : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseStoredTime(string $time): ?array
    {
        $trimmedTime = trim($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }
}
