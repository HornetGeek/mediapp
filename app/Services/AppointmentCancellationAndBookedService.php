<?php

namespace App\Services;

use App\Events\SendNotificationEvent;
use App\Helpers\ApiResponse;
use App\Http\Resources\AppointmentsResource;
use App\Models\Appointment;
use Carbon\Carbon;

class AppointmentCancellationAndBookedService
{


    public function cancel($bookId, $reps)
    {
        $appointment = Appointment::where('id', $bookId)
            ->where('representative_id', $reps->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        if ($appointment->status === 'cancelled') {
            return ApiResponse::sendResponse(400, 'Appointment is already cancelled', []);
        }
        if ($appointment->status === 'deleted') {
            return ApiResponse::sendResponse(400, 'Appointment is already deleted', []);
        }
        if ($appointment->status === 'left') {
            return ApiResponse::sendResponse(400, 'You can\'t cancel appointment in left status', []);
        }
        if ($appointment->status === 'suspended') {
            $appointment->update([
                'status' => 'deleted',
                'cancelled_by' => 'Reps.' . $reps->name,
            ]);

            $this->notifyDoctor($appointment, $reps);

            return ApiResponse::sendResponse(200, 'Appointment cancelled successfully', new AppointmentsResource($appointment));
        }

        $dateNow = Carbon::now('Asia/Riyadh');
        $parseAppointmentDate = Carbon::parse($appointment->date)->format('Y-m-d');
        $startTimeAppointment = $appointment->start_time->setTimezone(config('app.timezone'))->format('h:i A');
        $twelvePmToday = '12:00 PM';

        if ($dateNow->format('Y-m-d') !== $parseAppointmentDate) {
            $appointment->update(['status' => 'cancelled', 'cancelled_by' => 'Reps.' . $reps->name]);

            $this->notifyDoctor($appointment, $reps);

            return ApiResponse::sendResponse(200, 'Appointment cancelled successfully', new AppointmentsResource($appointment));

        } elseif ($dateNow->format('h:i A') < $twelvePmToday && $dateNow->format('h:i A') < $startTimeAppointment) {
            $appointment->update(['status' => 'cancelled']);

            $this->notifyDoctor($appointment, $reps);

            return ApiResponse::sendResponse(200, 'Appointment cancelled successfully', new AppointmentsResource($appointment));

        } else {

            return ApiResponse::sendResponse(400, 'You can\'t cancel appointment', []);
        }
    }

    public function completed($bookId, $reps)
    {
        $appointment = Appointment::where('id', $bookId)
            ->where('representative_id', $reps->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        if ($appointment->status === 'confirmed') {
            return ApiResponse::sendResponse(400, 'Appointment is already confirmed', []);
        }

        $appointment->update(['status' => 'confirmed']);

        $this->notifyDoctor($appointment, $reps);

        return ApiResponse::sendResponse(200, 'Appointment confirmed successfully', new AppointmentsResource($appointment));
    }

    public function changeStatus($appointmentId, $reps)
    {
        $appointment = Appointment::where('id', $appointmentId)
            ->where('representative_id', $reps->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        if ($appointment->status === 'confirmed') {
            return ApiResponse::sendResponse(400, 'Appointment is already confirmed', []);
        }

        if ($appointment->status !== 'suspended') {
            return ApiResponse::sendResponse(409, 'You can only change status for suspended appointments.', []);
        }

        $appointmentStartAt = $this->dateTimeFromAppointment($appointment, 'start_time');
        $appointmentEndAt = $this->dateTimeFromAppointment($appointment, 'end_time');

        if (!$appointmentStartAt || !$appointmentEndAt) {
            return ApiResponse::sendResponse(422, 'Appointment date or time is invalid.', []);
        }

        $now = Carbon::now('Africa/Cairo');

        if (!$now->greaterThan($appointmentEndAt)) {
            return ApiResponse::sendResponse(409, 'You can\'t change the status before the appointment end time.', []);
        }

        $deadlineAt = $appointmentStartAt->copy()->addHours(48);
        if ($now->greaterThan($deadlineAt)) {
            return ApiResponse::sendResponse(403, 'You can\'t change status after 48 hours from the appointment start time', []);
        }

        $appointment->update([
            'status' => 'confirmed',
            'cancelled_by' => null,
        ]);

        $this->notifySuccessDoctor($appointment, $reps);

        return ApiResponse::sendResponse(200, 'Change Status successfully', new AppointmentsResource($appointment));
    }

    private function dateTimeFromAppointment(Appointment $appointment, string $timeColumn): ?Carbon
    {
        $dateValue = trim((string) $appointment->getRawOriginal('date'));
        $timeValue = trim((string) $appointment->getRawOriginal($timeColumn));

        if ($dateValue === '' || $timeValue === '') {
            return null;
        }

        try {
            $datePart = Carbon::parse($dateValue, 'Africa/Cairo')->format('Y-m-d');
            $timePart = Carbon::parse($timeValue, 'Africa/Cairo')->format('H:i:s');

            return Carbon::createFromFormat('Y-m-d H:i:s', $datePart . ' ' . $timePart, 'Africa/Cairo');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function notifyDoctor($appointment, $reps)
    {
        $doctor = $appointment->doctor;

        $date = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->start_time->format('g:i A'));
        $formatted = $date->format('D d, M g:i A');

        $message = 'Visit With ' . $reps->name . ' has been cancelled by the representative. ' . $formatted;
        $dedupeKey = sprintf(
            'appointment:%d:rep_cancel:%s:to:doctor:%d',
            (int) $appointment->id,
            (string) $appointment->status,
            (int) $doctor->id
        );

        event(new SendNotificationEvent($doctor, 'Visit Cancelled by Rep', $message, 'doctor', [], $dedupeKey));
    }

    private function notifySuccessDoctor($appointment, $reps)
    {
        $doctor = $appointment->doctor;

        $date = Carbon::parse($appointment->date->format('Y-m-d') . ' ' . $appointment->start_time->format('g:i A'));
        $formatted = $date->format('D d, M g:i A');

        $message = 'Visit With ' . $reps->name . ' has been Confirmed by the representative. ' . $formatted;
        $dedupeKey = sprintf(
            'appointment:%d:rep_confirmed:to:doctor:%d',
            (int) $appointment->id,
            (int) $doctor->id
        );

        event(new SendNotificationEvent($doctor, 'Visit Confirmed by Rep', $message, 'doctor', [], $dedupeKey));
    }
}
