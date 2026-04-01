<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AppointmentStatusRefreshService
{
    private const TIMEZONE = 'Africa/Cairo';

    public function refreshForRepresentative(int $representativeId): void
    {
        $scope = Appointment::query()->where('representative_id', $representativeId);
        $this->refreshStatuses($scope);
    }

    public function refreshForDoctor(int $doctorId): void
    {
        $scope = Appointment::query()->where('doctors_id', $doctorId);
        $this->refreshStatuses($scope);
    }

    private function refreshStatuses(Builder $scope): void
    {
        $now = Carbon::now(self::TIMEZONE);

        $pendingAppointments = (clone $scope)
            ->where('status', 'pending')
            ->get(['id', 'date', 'end_time']);

        $suspendedAppointments = (clone $scope)
            ->where('status', 'suspended')
            ->get(['id', 'date', 'start_time']);

        $pendingToSuspendIds = $pendingAppointments
            ->filter(function (Appointment $appointment) use ($now): bool {
                $endAt = $this->dateTimeFromAppointment($appointment, 'end_time');
                if (!$endAt) {
                    return false;
                }

                return $now->greaterThanOrEqualTo($endAt->addMinute());
            })
            ->pluck('id')
            ->all();

        if (!empty($pendingToSuspendIds)) {
            Appointment::whereIn('id', $pendingToSuspendIds)
                ->update([
                    'status' => 'suspended',
                    'cancelled_by' => 'system',
                ]);
        }

        $suspendedToLeftIds = $suspendedAppointments
            ->filter(function (Appointment $appointment) use ($now): bool {
                $startAt = $this->dateTimeFromAppointment($appointment, 'start_time');
                if (!$startAt) {
                    return false;
                }

                return $now->greaterThanOrEqualTo($startAt->addHours(48));
            })
            ->pluck('id')
            ->all();

        if (!empty($suspendedToLeftIds)) {
            Appointment::whereIn('id', $suspendedToLeftIds)
                ->update([
                    'status' => 'left',
                    'cancelled_by' => 'system',
                ]);
        }
    }

    private function dateTimeFromAppointment(Appointment $appointment, string $timeColumn): ?Carbon
    {
        $dateValue = trim((string) $appointment->getRawOriginal('date'));
        $timeValue = trim((string) $appointment->getRawOriginal($timeColumn));

        if ($dateValue === '' || $timeValue === '') {
            return null;
        }

        try {
            $datePart = Carbon::parse($dateValue, self::TIMEZONE)->format('Y-m-d');
            $timePart = Carbon::parse($timeValue, self::TIMEZONE)->format('H:i:s');

            return Carbon::createFromFormat('Y-m-d H:i:s', $datePart . ' ' . $timePart, self::TIMEZONE);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
