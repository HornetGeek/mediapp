<?php

namespace App\Services;

use App\Events\SendNotificationEvent;
use App\Models\Doctors;
use App\Models\Notification;
use Carbon\Carbon;

class DoctorProfileCompletionReminderService
{
    private const TIMEZONE = 'Africa/Cairo';
    private const REMINDER_PREFIX = 'doctor_profile_completion_reminder';
    private const FIRST_REMINDER_DELAY_HOURS = 5;

    /**
     * @return array{stage: string, dedupe_key: string, missing_fields: array<int, string>}|null
     */
    public function sendReminderIfNeeded(Doctors $doctor, ?Carbon $now = null): ?array
    {
        $now = $this->resolveNow($now);
        $missingFields = $this->missingProfileFields($doctor);

        if (empty($missingFields)) {
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
            $this->buildPayload($reminder['stage'], $missingFields),
            $reminder['dedupe_key']
        ));

        return [
            'stage' => $reminder['stage'],
            'dedupe_key' => $reminder['dedupe_key'],
            'missing_fields' => $missingFields,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function missingProfileFields(Doctors $doctor): array
    {
        return $doctor->missingProfileFields();
    }

    /**
     * @return array{stage: string, title: string, body: string, dedupe_key: string}|null
     */
    private function resolveReminder(Doctors $doctor, Carbon $now): ?array
    {
        $createdAt = $doctor->created_at instanceof Carbon
            ? $doctor->created_at->copy()->setTimezone(self::TIMEZONE)
            : $now->copy();

        if ($createdAt->greaterThan($now->copy()->subHours(self::FIRST_REMINDER_DELAY_HOURS))) {
            return null;
        }

        $period = $now->toDateString();
        $stage = $this->hasAnyReminder($doctor) ? 'daily' : 'first';
        if ($this->latestReminderForPeriod($doctor, $period) !== null) {
            return null;
        }

        return [
            'stage' => $stage,
            'title' => 'أكمل بيانات ملفك الشخصي',
            'body' => 'أكمل بيانات ملفك الشخصي ليتمكن المندوبون من العثور عليك وحجز الزيارات.',
            'dedupe_key' => sprintf(
                '%s:%s:doctor:%d:period:%s',
                self::REMINDER_PREFIX,
                $stage,
                (int) $doctor->id,
                $period
            ),
        ];
    }

    /**
     * @param array<int, string> $missingFields
     * @return array<string, mixed>
     */
    private function buildPayload(string $stage, array $missingFields): array
    {
        return [
            'type' => self::REMINDER_PREFIX,
            'action_type' => 'open_doctor_profile',
            'screen' => 'doctor_profile_edit',
            'deep_link' => 'mediapp://doctor/profile/edit',
            'stage' => $stage,
            'missing_fields' => array_values($missingFields),
            'target_type' => 'doctor',
            'delivery_type' => 'both',
        ];
    }

    private function hasAnyReminder(Doctors $doctor): bool
    {
        return Notification::query()
            ->where('notifiable_id', (int) $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->where('dedupe_key', 'like', self::REMINDER_PREFIX . ':%:doctor:' . (int) $doctor->id . ':%')
            ->exists();
    }

    private function latestReminderForPeriod(Doctors $doctor, string $period): ?Notification
    {
        return Notification::query()
            ->where('notifiable_id', (int) $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->where('dedupe_key', 'like', self::REMINDER_PREFIX . ':%:doctor:' . (int) $doctor->id . ':period:' . $period)
            ->latest()
            ->first();
    }

    private function resolveNow(?Carbon $now): Carbon
    {
        return $now?->copy()->setTimezone(self::TIMEZONE) ?? Carbon::now(self::TIMEZONE);
    }
}
