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

        $now = Carbon::now();
        $dateInCairo = $now->setTimezone('Africa/Cairo');

        $appointmentDate = Carbon::parse($appointment->date);
        $endTime = $appointment->getRawOriginal('end_time');

        $endTimeCarbon = Carbon::parse($endTime);

        $appointmentEnd = $endTimeCarbon->copy()->setDate(
            $appointmentDate->year,
            $appointmentDate->month,
            $appointmentDate->day
        );
        // dd($appointment->end_time->format('h:i A l'), $dateInCairo->format("h:i A l"));
        $diffHours = $now->diffInHours($appointmentDate, false);
        /*
        diffInHours($appointmentDate, false)
        - قيمة موجبة لو الآن بعد الموعد
        - قيمة سالبة لو الموعد في المستقبل
        */

        if ($diffHours < -48) {
            return ApiResponse::sendResponse(403, 'You can\'t change status after 48 hours from the appointment time', []);
        }

        if ($dateInCairo->isSameDay($appointmentDate)) {
            if ($dateInCairo->greaterThan($appointmentEnd)) {
                $appointment->update(['status' => 'confirmed']);
                $this->notifySuccessDoctor($appointment, $reps);

                return ApiResponse::sendResponse(200, 'Change Status successfully', new AppointmentsResource($appointment));
            }
            return ApiResponse::sendResponse(200, 'You can\'t change the status before the appointment end time.', []);
        }
        return ApiResponse::sendResponse(409, 'You can\'t change the status before the appointment date.', []);

        // if ($dateInCairo->format('Y-m-d') === $appointmentDate->format('Y-m-d') && $dateInCairo->format("h:i A l") > $appointment->end_time->format('h:i A l')) {
        //     $appointment->update(['status' => 'confirmed']);
        //     $this->notifyDoctor($appointment, $reps);
        //     return ApiResponse::sendResponse(200, 'Change Status successfully', new AppointmentsResource($appointment));
        // } else {
        //     return ApiResponse::sendResponse(200, 'You can\'t change the status before the reservation date.', []);
        // }



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
