<?php

namespace App\Services;

use Carbon\Carbon;

class AppointmentBookingInputService
{
    private const TIMEZONE = 'Africa/Cairo';
    private const DATE_FORMAT = 'Y-m-d';

    public function buildSlot(?string $date, ?string $startTime, int $durationMinutes = 5): array
    {
        $normalizedDate = $this->normalizeDate($date);
        if (isset($normalizedDate['error'])) {
            return $normalizedDate;
        }

        $normalizedStartTime = $this->normalizeStartTime($startTime);
        if (isset($normalizedStartTime['error'])) {
            return $normalizedStartTime;
        }

        try {
            $slotStartAt = Carbon::createFromFormat(
                self::DATE_FORMAT . ' H:i:s',
                $normalizedDate['date'] . ' ' . $normalizedStartTime['start_time'],
                self::TIMEZONE
            );
        } catch (\Throwable $exception) {
            return ['error' => 'Invalid appointment date or start_time'];
        }

        if ($slotStartAt->format(self::DATE_FORMAT . ' H:i:s') !== $normalizedDate['date'] . ' ' . $normalizedStartTime['start_time']) {
            return ['error' => 'Invalid appointment date or start_time'];
        }

        $slotEndAt = $slotStartAt->copy()->addMinutes($durationMinutes);
        if ($slotEndAt->toDateString() !== $slotStartAt->toDateString()) {
            return ['error' => 'Appointment end time cannot roll to the next day'];
        }

        return [
            'date' => $normalizedDate['date'],
            'start_time' => $slotStartAt->format('H:i:s'),
            'end_time' => $slotEndAt->format('H:i:s'),
            'start_at' => $slotStartAt,
            'end_at' => $slotEndAt,
        ];
    }

    private function normalizeDate(?string $date): array
    {
        $dateValue = trim((string) $date);
        if ($dateValue === '') {
            return ['error' => 'date is required'];
        }

        try {
            $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $dateValue, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return ['error' => 'date must be in Y-m-d format'];
        }

        if ($parsedDate->format(self::DATE_FORMAT) !== $dateValue) {
            return ['error' => 'date must be in Y-m-d format'];
        }

        return [
            'date' => $parsedDate->format(self::DATE_FORMAT),
        ];
    }

    private function normalizeStartTime(?string $startTime): array
    {
        $timeValue = trim((string) $startTime);
        if ($timeValue === '') {
            return ['error' => 'start_time is required'];
        }

        if (preg_match('/^24(?::|$)/', $timeValue) === 1) {
            return ['error' => 'start_time cannot use 24:* values'];
        }

        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*([AaPp][Mm])$/', $timeValue, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $meridiem = strtoupper($matches[3]);

            if ($meridiem === 'AM' && $hour === 12) {
                $hour = 0;
            } elseif ($meridiem === 'PM' && $hour !== 12) {
                $hour += 12;
            }

            return [
                'start_time' => sprintf('%02d:%02d:00', $hour, $minute),
            ];
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $timeValue, $matches) === 1) {
            return [
                'start_time' => sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]),
            ];
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $timeValue, $matches) === 1) {
            return [
                'start_time' => sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]),
            ];
        }

        return ['error' => 'start_time format is invalid. Use hh:mm AM/PM, HH:mm, or HH:mm:ss'];
    }
}
