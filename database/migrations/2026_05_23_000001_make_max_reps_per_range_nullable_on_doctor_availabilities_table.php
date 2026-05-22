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
        if (!Schema::hasTable('doctor_availabilities')
            || !Schema::hasColumn('doctor_availabilities', 'max_reps_per_range')) {
            return;
        }

        Schema::table('doctor_availabilities', function (Blueprint $table) {
            $table->unsignedInteger('max_reps_per_range')
                ->nullable()
                ->default(null)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('doctor_availabilities')
            || !Schema::hasColumn('doctor_availabilities', 'max_reps_per_range')) {
            return;
        }

        DB::table('doctor_availabilities')
            ->whereNull('max_reps_per_range')
            ->update(['max_reps_per_range' => 2]);

        Schema::table('doctor_availabilities', function (Blueprint $table) {
            $table->unsignedInteger('max_reps_per_range')
                ->default(2)
                ->nullable(false)
                ->change();
        });
    }
};
