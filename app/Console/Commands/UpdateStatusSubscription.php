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
            $doctorCounts = [];
            $doctorsById = [];
            $repCounts = [];
            $repsById = [];

            foreach ($company->appointments as $appointment) {

                if ($appointment->status === 'cancelled') {
                    continue;
                }

                $doctor = $appointment->doctor;
                $rep = $appointment->representative;

                if ($doctor && $doctor->id) {
                    $doctorId = (int) $doctor->id;
                    $doctorCounts[$doctorId] = ($doctorCounts[$doctorId] ?? 0) + 1;
                    $doctorsById[$doctorId] = $doctor;
                }

                if ($rep && $rep->id) {
                    $repId = (int) $rep->id;
                    $repCounts[$repId] = ($repCounts[$repId] ?? 0) + 1;
                    $repsById[$repId] = $rep;
                }

                $appointment->update([
                    'status' => 'cancelled',
                    'cancelled_by' => 'system',
                ]);
            }

            foreach ($doctorCounts as $doctorId => $count) {
                $doctor = $doctorsById[$doctorId];
                $dedupeKey = "subscription_expiry:company:{$company->id}:doctor:{$doctorId}:{$now->toDateString()}";

                event(new SendNotificationEvent(
                    $doctor,
                    'Visit Cancelled Due to Company Subscription Expiry',
                    "{$count} visit(s) cancelled due to an issue with {$company->name}’s account.",
                    'doctor',
                    [],
                    $dedupeKey
                ));
            }

            foreach ($repCounts as $repId => $count) {
                $rep = $repsById[$repId];
                $dedupeKey = "subscription_expiry:company:{$company->id}:reps:{$repId}:{$now->toDateString()}";

                event(new SendNotificationEvent(
                    $rep,
                    'Company Subscription Expired',
                    "{$count} of your visit(s) were cancelled because your company subscription has expired. Contact your admin.",
                    'reps',
                    [],
                    $dedupeKey
                ));
            }
        }
    }
}
