<?php

namespace App\Services;

use App\Events\SendNotificationEvent;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DoctorAvailabilityReminderService
{
    private const TIMEZONE = 'Africa/Cairo';
    private const REMINDER_PREFIX = 'doctor_availability_reminder';
    private const OPEN_CAPACITY_HORIZON_DAYS = 14;
    private const MONTHLY_PLANNING_HORIZON_DAYS = 30;

    /**
     * @return array{stage: string, dedupe_key: string}|null
     */
    public function sendReminderIfNeeded(Doctors $doctor, ?Carbon $now = null): ?array
    {
        $now = $this->resolveNow($now);

        if ((string) $doctor->status !== 'active') {
            return null;
        }

        if ($this->hasOpenCapacity($doctor, self::OPEN_CAPACITY_HORIZON_DAYS, $now)) {
            return null;
        }

        $reminder = $this->resolveReminder($doctor, $now);
        if ($reminder === null) {
            return null;
        }

        event(new SendNotificationEvent(
            $doctor,
            $reminder['title'],
            $reminder['body'],
            'doctor',
            $this->buildPayload($reminder['stage']),
            $reminder['dedupe_key']
        ));

        return [
            'stage' => $reminder['stage'],
            'dedupe_key' => $reminder['dedupe_key'],
        ];
    }

    public function hasOpenCapacity(
        Doctors $doctor,
        int $horizonDays = self::OPEN_CAPACITY_HORIZON_DAYS,
        ?Carbon $now = null
    ): bool {
        $now = $this->resolveNow($now);
        $startDate = $now->copy()->startOfDay();
        $endDate = $startDate->copy()->addDays(max(0, $horizonDays));

        $availabilities = DoctorAvailability::query()
            ->where('doctors_id', (int) $doctor->id)
            ->where('status', 'available')
            ->get();

        if ($availabilities->isEmpty()) {
            return false;
        }

        $bookedCounts = $this->loadBookedCounts(
            (int) $doctor->id,
            $startDate->toDateString(),
            $endDate->toDateString()
        );

        foreach ($availabilities as $availability) {
            foreach ($this->occurrenceDatesWithinHorizon($availability, $startDate, $endDate) as $occurrenceDate) {
                $interval = $this->buildAvailabilityInterval($availability, $occurrenceDate);
                if ($interval === null || $interval['start_at']->lessThanOrEqualTo($now)) {
                    continue;
                }

                $maxRepsPerRange = $availability->max_reps_per_range;
                if ($maxRepsPerRange === null) {
                    return true;
                }

                $bookedCount = (int) (
                    $bookedCounts[(int) $availability->id][$occurrenceDate->toDateString()] ?? 0
                );

                if ($bookedCount < max(1, (int) $maxRepsPerRange)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{stage: string, title: string, body: string, dedupe_key: string}|null
     */
    private function resolveReminder(Doctors $doctor, Carbon $now): ?array
    {
        $hasAnyAvailability = DoctorAvailability::query()
            ->where('doctors_id', (int) $doctor->id)
            ->exists();

        if (!$hasAnyAvailability) {
            return $this->resolveNoAvailabilityReminder($doctor, $now);
        }

        if ((int) $now->day === 25
            && !$this->hasOpenCapacity($doctor, self::MONTHLY_PLANNING_HORIZON_DAYS, $now)
            && $this->latestReminderForStage($doctor, 'monthly_planning', $now->format('Y-m')) === null
        ) {
            return $this->buildReminder(
                $doctor,
                'monthly_planning',
                $this->periodKey($now, 'month'),
                'حدد مواعيدك للشهر القادم ليتمكن المندوبون من حجز زياراتهم.',
                'month'
            );
        }

        $weekPeriod = $this->periodKey($now, 'week');
        if ($this->latestReminderForStage($doctor, 'capacity_empty', $weekPeriod) !== null) {
            return null;
        }

        return $this->buildReminder(
            $doctor,
            'capacity_empty',
            $weekPeriod,
            'مواعيدك القادمة ممتلئة. أضف وقتًا جديدًا لاستقبال زيارات أكثر.',
            'week'
        );
    }

    /**
     * @return array{stage: string, title: string, body: string, dedupe_key: string}|null
     */
    private function resolveNoAvailabilityReminder(Doctors $doctor, Carbon $now): ?array
    {
        $firstSetup = $this->latestReminderForStage($doctor, 'first_setup');
        if ($firstSetup === null) {
            $createdAt = $doctor->created_at instanceof Carbon
                ? $doctor->created_at->copy()->setTimezone(self::TIMEZONE)
                : $now->copy();

            if ($createdAt->greaterThan($now->copy()->subDay())) {
                return null;
            }

            return $this->buildReminder(
                $doctor,
                'first_setup',
                $this->periodKey($now, 'day'),
                'حدد مواعيدك المتاحة ليتمكن المندوبون من حجز زياراتهم.'
            );
        }

        $followUp3d = $this->latestReminderForStage($doctor, 'follow_up_3d');
        if ($followUp3d === null && $firstSetup->created_at?->copy()->addDays(3)->lessThanOrEqualTo($now)) {
            return $this->buildReminder(
                $doctor,
                'follow_up_3d',
                $this->periodKey($now, 'day'),
                'حدد مواعيدك المتاحة ليتمكن المندوبون من حجز زياراتهم.'
            );
        }

        if ($followUp3d !== null
            && $this->latestReminderForStage($doctor, 'follow_up_7d') === null
            && $followUp3d->created_at?->copy()->addDays(7)->lessThanOrEqualTo($now)
        ) {
            return $this->buildReminder(
                $doctor,
                'follow_up_7d',
                $this->periodKey($now, 'day'),
                'حدد مواعيدك المتاحة ليتمكن المندوبون من حجز زياراتهم.'
            );
        }

        return null;
    }

    /**
     * @return array{stage: string, title: string, body: string, dedupe_key: string}
     */
    private function buildReminder(
        Doctors $doctor,
        string $stage,
        string $period,
        string $body,
        string $periodLabel = 'period'
    ): array
    {
        return [
            'stage' => $stage,
            'title' => 'أضف مواعيد الزيارات',
            'body' => $body,
            'dedupe_key' => sprintf(
                '%s:%s:doctor:%d:%s:%s',
                self::REMINDER_PREFIX,
                $stage,
                (int) $doctor->id,
                $periodLabel,
                $period
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildPayload(string $stage): array
    {
        return [
            'type' => 'doctor_availability_reminder',
            'action_type' => 'open_availability_setup',
            'screen' => 'doctor_availability_setup',
            'deep_link' => 'mediapp://doctor/availability/setup',
            'stage' => $stage,
            'target_type' => 'doctor',
            'delivery_type' => 'both',
        ];
    }

    private function latestReminderForStage(Doctors $doctor, string $stage, ?string $period = null): ?Notification
    {
        $prefix = sprintf('%s:%s:doctor:%d:', self::REMINDER_PREFIX, $stage, (int) $doctor->id);

        return Notification::query()
            ->where('notifiable_id', (int) $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->where('dedupe_key', 'like', $prefix . '%')
            ->when($period !== null, function ($query) use ($period) {
                $query->where('dedupe_key', 'like', '%' . $period);
            })
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function loadBookedCounts(int $doctorId, string $fromDate, string $toDate): array
    {
        $rows = Appointment::query()
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
            $date = Carbon::parse((string) $row->date, self::TIMEZONE)->toDateString();
            $counts[$availabilityId][$date] = (int) $row->booked_reps_count;
        }

        return $counts;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function occurrenceDatesWithinHorizon(
        DoctorAvailability $availability,
        Carbon $startDate,
        Carbon $endDate
    ): Collection {
        $fixedDate = $this->parseFixedCalendarDate((string) $availability->date);
        if ($fixedDate !== null) {
            return $fixedDate->betweenIncluded($startDate, $endDate)
                ? collect([$fixedDate])
                : collect();
        }

        $weekday = $this->normalizeWeekdayName((string) $availability->date);
        if ($weekday === null) {
            return collect();
        }

        $dates = collect();
        $candidate = $startDate->copy();
        while ($candidate->lessThanOrEqualTo($endDate)) {
            if (strtolower($candidate->format('l')) === $weekday) {
                $dates->push($candidate->copy());
            }
            $candidate->addDay();
        }

        return $dates;
    }

    /**
     * @return array{start_at: Carbon, end_at: Carbon}|null
     */
    private function buildAvailabilityInterval(DoctorAvailability $availability, Carbon $anchorDate): ?array
    {
        $startTimeParts = $this->parseStoredTime((string) $availability->start_time);
        $endTimeParts = $this->parseStoredTime((string) $availability->end_time);
        if ($startTimeParts === null || $endTimeParts === null) {
            return null;
        }

        $startAt = $anchorDate->copy()->setTime($startTimeParts[0], $startTimeParts[1], $startTimeParts[2]);
        $endAt = $anchorDate->copy()->setTime($endTimeParts[0], $endTimeParts[1], $endTimeParts[2]);

        if ((bool) $availability->ends_next_day || $endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function parseFixedCalendarDate(string $value): ?Carbon
    {
        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return null;
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $trimmedValue, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($parsedDate->format('Y-m-d') !== $trimmedValue) {
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

    private function periodKey(Carbon $now, string $type): string
    {
        return match ($type) {
            'week' => $now->format('o-\WW'),
            'month' => $now->format('Y-m'),
            default => $now->toDateString(),
        };
    }

    private function resolveNow(?Carbon $now): Carbon
    {
        return ($now ?? Carbon::now(self::TIMEZONE))->copy()->setTimezone(self::TIMEZONE);
    }
}
