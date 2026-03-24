<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStatusAppointmentToSuspended extends Command
{
    protected $signature = 'app:update-status-appointment-to-suspended';

    protected $description = 'Command description';


    public function handle()
    {
        $now = Carbon::now();

        // ->whereRaw("TIMESTAMPDIFF(HOUR, CONCAT(date, ' ', end_time), ?) >= 1", [$now])
        $appointments = Appointment::where('status', 'pending')
             ->whereRaw("TIMESTAMPDIFF(MINUTE, CONCAT(date, ' ', end_time), ?) >= 1", [$now])
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No appointments to update.');
            return 0;
        }

        foreach ($appointments as $appointment) {
            $appointment->update(['status' => 'suspended', 'cancelled_by' => 'system']);
            $this->info("Appointment ID {$appointment->id} marked as suspended.");
        }

        $this->info('All eligible appointments have been updated successfully.');
        return 0;
    }
}
