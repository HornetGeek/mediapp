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

        if (!Schema::hasColumn('doctor_availabilities', 'visit_time_type')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->enum('visit_time_type', ['before', 'after', 'between'])
                    ->nullable()
                    ->default('between')
                    ->after('max_reps_per_range');
            });
        }

        DB::table('doctor_availabilities')
            ->whereNull('visit_time_type')
            ->update(['visit_time_type' => 'between']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('doctor_availabilities')) {
            return;
        }

        if (Schema::hasColumn('doctor_availabilities', 'visit_time_type')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->dropColumn('visit_time_type');
            });
        }
    }
};
