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

        if (!Schema::hasColumn('doctor_availabilities', 'ends_next_day')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->boolean('ends_next_day')->default(false)->after('end_time');
            });
        }

        DB::table('doctor_availabilities')
            ->whereColumn('end_time', '<', 'start_time')
            ->update(['ends_next_day' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('doctor_availabilities')) {
            return;
        }

        if (Schema::hasColumn('doctor_availabilities', 'ends_next_day')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->dropColumn('ends_next_day');
            });
        }
    }
};
