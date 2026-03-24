<?php

namespace App\Console\Commands;

use App\Events\SendNotificationEvent;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReminderSubscriptionForCompany extends Command
{

    protected $signature = 'app:send-reminder-subscription-for-company';

    protected $description = 'Send reminder subscription for company';


    public function handle()
    {
        Log::info('Cron company:subscription-reminder started at ' . now());

        $now = Carbon::now();
        $targetDate = $now->copy()->addDays(15);

        $companies = Company::whereBetween('subscription_end', [$now, $targetDate])
            ->whereNotNull('fcm_token')
            ->get();
        // $targetDate = Carbon::now()->addDays(15)->startOfDay();

        // $companies = Company::whereDate('subscription_end', $targetDate)
        //     ->whereNotNull('fcm_token')
        //     ->get();

        foreach ($companies as $company) {
            event(new SendNotificationEvent(
                $company,
                'Subscription expiration reminder',
                'Your subscription will expire in 15 days. Please renew to avoid service interruption.',
                'company'
            ));
        }
        Log::info('Now: ' . $now->toDateString());
        Log::info('Target Date: ' . $targetDate->toDateString());
        Log::info('Companies count: ' . $companies->count());
        Log::info('Cron company:subscription-reminder finished at ' . now());
        $this->info('Reminders sent successfully.');
    }
}
