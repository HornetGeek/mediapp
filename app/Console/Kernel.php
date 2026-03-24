<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
     /**
      * Define the application's command schedule.
      */
     protected function schedule(Schedule $schedule): void
     {

          // turn on the command every first day of the month at 8 am
          // $schedule->command('doctor:monthly-reminder')->monthlyOn(1, '08:00');

          // $schedule->command('doctor:monthly-reminder')->everyMinute();

          $schedule->command('appointments:update-left')->everyMinute()->withoutOverlapping()->onFailure(function () {
               Log::error('appointments:update-left failed');
          });
          $schedule->command('app:send-reminder-subscription-for-company')->dailyAt('01:00')->onFailure(function () {
               Log::error('app:send-reminder-subscription-for-company failed');
          });
          $schedule->command('app:update-status-appointment-to-suspended')->everyMinute()->withoutOverlapping()->onFailure(function () {
               Log::error('app:update-status-appointment-to-suspended failed');
          });
          $schedule->command('companies:check-subscriptions')->dailyAt('01:00')->withoutOverlapping()->onFailure(function () {
               Log::error('companies:check-subscriptions failed');
          }); // dailyAt('02:00')

          $schedule->command('app:update-status-doctor')->everyMinute(); // dailyAt('03:00')

          // $schedule->command('test:cron')->everyMinute();
     }

     /**
      * Register the commands for the application.
      */
     protected function commands(): void
     {
          $this->load(__DIR__ . '/Commands');

          require base_path('routes/console.php');
     }
}
