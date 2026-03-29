<?php

namespace App\Console\Commands;

use App\Models\Package;
use Illuminate\Console\Command;

class ReportCustomDayPackages extends Command
{
    protected $signature = 'packages:report-custom-days';

    protected $description = 'Report packages that still use legacy custom day durations';

    public function handle(): int
    {
        $packages = Package::query()
            ->where('plan_type', Package::PLAN_CUSTOM_DAYS)
            ->orderBy('id')
            ->get(['id', 'name', 'duration', 'price', 'plan_type', 'billing_months']);

        if ($packages->isEmpty()) {
            $this->info('No legacy custom-day packages found.');
            return self::SUCCESS;
        }

        $this->warn('Legacy custom-day packages detected:');
        $this->table(
            ['ID', 'Name', 'Duration (Days)', 'Price', 'Plan Type', 'Billing Months'],
            $packages->map(function ($package) {
                return [
                    $package->id,
                    $package->name,
                    $package->duration,
                    $package->price,
                    $package->plan_type,
                    $package->billing_months ?? '-',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }
}
