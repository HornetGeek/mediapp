<?php

namespace App\Console\Commands;

use App\Events\SendNotificationEvent;
use App\Models\Doctors;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMonthlyAvailabilityReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doctor:monthly-reminder';
    protected $description = 'Send monthly availability reminder to doctors';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Cron doctor:monthly-reminder started at ' . now());
        $doctors = Doctors::all();
        $currentMonth = now()->format('Y-m');

        foreach ($doctors as $doctor) {
            $dedupeKey = sprintf(
                'monthly_availability_reminder:doctor:%d:month:%s',
                (int) $doctor->id,
                $currentMonth
            );

            event(new SendNotificationEvent(
                $doctor,
                'إعداد المواعيد الجديدة',
                'اختر إذا كنت تريد نسخ المواعيد من الشهر السابق أو تعديلها.',
                [
                    'action_type' => 'availabilities_setup',
                    'option_copy_last_month' => 'api/doctor/availabilities/copy-last-month',
                    'option_edit_times' => 'api/doctor/availabilities/save'
                ],
                [],
                $dedupeKey
            ));
        }
        Log::info('Cron doctor:monthly-reminder finished at ' . now());
        $this->info('Monthly reminders sent to all doctors.');
    }
}
