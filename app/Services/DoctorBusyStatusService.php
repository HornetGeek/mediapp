<?php

namespace App\Services;

use App\Models\Doctors;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DoctorBusyStatusService
{
    private const TIMEZONE = 'Africa/Cairo';
    private const DATE_FORMAT = 'Y-m-d';

    public function validateBusyRangeInput(?string $fromDate, ?string $toDate): ?string
    {
        $fromDateValue = trim((string) $fromDate);
        $toDateValue = trim((string) $toDate);

        if ($fromDateValue === '' || $toDateValue === '') {
            return 'from_date and to_date are required when status is busy';
        }

        $from = $this->parseDate($fromDateValue);
        if ($from === null) {
            return 'from_date must be in Y-m-d format';
        }

        $to = $this->parseDate($toDateValue);
        if ($to === null) {
            return 'to_date must be in Y-m-d format';
        }

        if ($from->greaterThan($to)) {
            return 'from_date must be before or equal to to_date';
        }

        return null;
    }

    public function normalizeDoctorBusyState(Doctors $doctor): Doctors
    {
        if ((string) $doctor->status !== 'busy') {
            return $doctor;
        }

        $busyPeriod = $this->buildBusyPeriodPayload($doctor);
        if ($busyPeriod !== null && $this->today()->lessThanOrEqualTo(Carbon::createFromFormat(self::DATE_FORMAT, $busyPeriod['to_date'], self::TIMEZONE))) {
            return $doctor;
        }

        $doctor->status = 'active';
        $doctor->from_date = null;
        $doctor->to_date = null;
        $doctor->save();

        return $doctor;
    }

    public function normalizeDoctorCollectionBusyState(iterable $doctors): void
    {
        if ($doctors instanceof Collection) {
            $doctors->each(function ($doctor): void {
                if ($doctor instanceof Doctors) {
                    $this->normalizeDoctorBusyState($doctor);
                }
            });

            return;
        }

        foreach ($doctors as $doctor) {
            if ($doctor instanceof Doctors) {
                $this->normalizeDoctorBusyState($doctor);
            }
        }
    }

    public function buildBusyPeriodPayload(Doctors $doctor): ?array
    {
        if ((string) $doctor->status !== 'busy') {
            return null;
        }

        $from = $this->parseDate((string) $doctor->from_date);
        $to = $this->parseDate((string) $doctor->to_date);

        if ($from === null || $to === null || $from->greaterThan($to)) {
            return null;
        }

        $today = $this->today();

        return [
            'from_date' => $from->format(self::DATE_FORMAT),
            'to_date' => $to->format(self::DATE_FORMAT),
            'is_active_now' => $today->betweenIncluded($from, $to),
        ];
    }

    public function isDateWithinBusyPeriod(Doctors $doctor, string $date): bool
    {
        $busyPeriod = $this->buildBusyPeriodPayload($doctor);
        if ($busyPeriod === null) {
            return false;
        }

        $bookingDate = $this->parseDate($date);
        if ($bookingDate === null) {
            return false;
        }

        return $bookingDate->format(self::DATE_FORMAT) >= $busyPeriod['from_date']
            && $bookingDate->format(self::DATE_FORMAT) <= $busyPeriod['to_date'];
    }

    public function formatDateForResponse(?string $date): ?string
    {
        $parsedDate = $this->parseDate((string) $date);
        if ($parsedDate === null) {
            return null;
        }

        return $parsedDate->format(self::DATE_FORMAT);
    }

    private function parseDate(string $date): ?Carbon
    {
        $dateValue = trim($date);
        if ($dateValue === '') {
            return null;
        }

        try {
            $parsedDate = Carbon::createFromFormat(self::DATE_FORMAT, $dateValue, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($parsedDate->format(self::DATE_FORMAT) !== $dateValue) {
            return null;
        }

        return $parsedDate->startOfDay();
    }

    private function today(): Carbon
    {
        return Carbon::now(self::TIMEZONE)->startOfDay();
    }
}
