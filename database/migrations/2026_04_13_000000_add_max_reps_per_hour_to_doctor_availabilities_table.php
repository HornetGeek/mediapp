<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('doctor_availabilities')) {
            return;
        }

        if (!Schema::hasColumn('doctor_availabilities', 'max_reps_per_hour')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->unsignedTinyInteger('max_reps_per_hour')->default(2)->after('ends_next_day');
            });
        }

        DB::table('doctor_availabilities')
            ->whereNull('max_reps_per_hour')
            ->update(['max_reps_per_hour' => 2]);

        DB::table('doctor_availabilities')
            ->whereNotIn('max_reps_per_hour', [1, 2])
            ->update(['max_reps_per_hour' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('doctor_availabilities')) {
            return;
        }

        if (Schema::hasColumn('doctor_availabilities', 'max_reps_per_hour')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->dropColumn('max_reps_per_hour');
            });
        }
    }
};
