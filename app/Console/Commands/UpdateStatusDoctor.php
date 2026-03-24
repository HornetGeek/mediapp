<?php

namespace App\Console\Commands;

use App\Models\Doctors;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateStatusDoctor extends Command
{

    protected $signature = 'app:update-status-doctor';
    protected $description = 'Command description';


    public function handle()
    {
        $now = Carbon::now();
        $getDateNow = $now->toDateString();
        $updated = Doctors::where('status', 'busy')
            ->whereDate('to_date', '<', $getDateNow)
            ->update(['status' => 'active']);


        $this->info("Updated {$updated} doctors successfully.");
    }
}
