<?php

namespace App\Console\Commands;

use App\Events\SendNotificationEvent;
use App\Models\Appointment;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStatusSubscription extends Command
{

    protected $signature = 'companies:check-subscriptions';
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::today();

        $companies = Company::where('status', 'active')
            ->whereDate('subscription_end', '<', $now)
            ->with([
                'appointments.doctor',
                'appointments.representative'
            ])
            ->get();

        foreach ($companies as $company) {

            $company->update(['status' => 'inactive']);

            foreach ($company->appointments as $appointment) {

                if ($appointment->status === 'cancelled') {
                    continue;
                }

                $doctor = $appointment->doctor;
                $rep = $appointment->representative;

                if ($doctor) {
                    event(new SendNotificationEvent(
                        $doctor,
                        'Visit Cancelled Due to Company Subscription Expiry',
                        "Visit with {$rep->name} cancelled due to an issue with {$company->name}’s account.",
                        'doctor'
                    ));
                }

                if ($rep) {
                    event(new SendNotificationEvent(
                        $rep,
                        'Company Subscription Expired',
                        'Your company’s subscription has expired. Contact your admin.',
                        'reps'
                    ));
                }

                $appointment->update([
                    'status' => 'cancelled',
                    'cancelled_by' => 'system',
                ]);
            }
        }
    }
}
