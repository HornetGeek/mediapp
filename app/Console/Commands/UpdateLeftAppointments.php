<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateLeftAppointments extends Command
{
    protected $signature = 'appointments:update-left';
    protected $description = 'Update appointments to "left" if 48 hours have passed after the appointment date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        // $appointments = Appointment::where('status', 'suspended')
        //     ->whereRaw("TIMESTAMPDIFF(HOUR, CONCAT(date, ' ', start_time), ?) > 48", [$now])
        //     ->get();
        $threshold = Carbon::now()->subHours(48);

        $appointments = Appointment::where('status', 'suspended')
            ->whereRaw("CONCAT(date, ' ', start_time) <= ?", [$threshold])
            ->get();

        if ($appointments->isEmpty()) {
            $this->info('No appointments to update.');
            return 0;
        }

        foreach ($appointments as $appointment) {
            $appointment->update(['status' => 'left', 'cancelled_by' => 'system']);
            $this->info("Appointment ID {$appointment->id} marked as left.");
        }

        $this->info('All eligible appointments have been updated successfully.');
        return 0;
    }
}
