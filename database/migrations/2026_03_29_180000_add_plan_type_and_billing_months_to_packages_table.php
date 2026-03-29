<?php

use App\Models\Package;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('plan_type')->default(Package::PLAN_CUSTOM_DAYS)->after('duration');
            $table->unsignedTinyInteger('billing_months')->nullable()->after('plan_type');
        });

        DB::table('packages')
            ->select(['id', 'duration'])
            ->orderBy('id')
            ->chunkById(100, function ($packages) {
                foreach ($packages as $package) {
                    $duration = (int) $package->duration;

                    $planType = match ($duration) {
                        90 => Package::PLAN_QUARTERLY,
                        180 => Package::PLAN_SEMI_ANNUAL,
                        365 => Package::PLAN_ANNUAL,
                        default => Package::PLAN_CUSTOM_DAYS,
                    };

                    $billingMonths = Package::PLAN_TO_MONTHS[$planType] ?? null;

                    DB::table('packages')
                        ->where('id', $package->id)
                        ->update([
                            'plan_type' => $planType,
                            'billing_months' => $billingMonths,
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['plan_type', 'billing_months']);
        });
    }
};
