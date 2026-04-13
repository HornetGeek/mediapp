<?php

use Carbon\Carbon;
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

        if (!Schema::hasColumn('doctor_availabilities', 'max_reps_per_range')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->unsignedInteger('max_reps_per_range')->default(1)->after('ends_next_day');
            });
        }

        if (Schema::hasColumn('doctor_availabilities', 'max_reps_per_hour')) {
            DB::table('doctor_availabilities')
                ->select('id', 'start_time', 'end_time', 'ends_next_day', 'max_reps_per_hour')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $durationMinutes = $this->calculateDurationMinutes(
                            (string) $row->start_time,
                            (string) $row->end_time,
                            (bool) $row->ends_next_day
                        );

                        $oldRate = (int) $row->max_reps_per_hour;
                        if (!in_array($oldRate, [1, 2], true)) {
                            $oldRate = 2;
                        }

                        $maxRepsPerRange = max(1, (int) floor(($durationMinutes * $oldRate) / 60));

                        DB::table('doctor_availabilities')
                            ->where('id', $row->id)
                            ->update(['max_reps_per_range' => $maxRepsPerRange]);
                    }
                });

            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->dropColumn('max_reps_per_hour');
            });
        }

        DB::table('doctor_availabilities')
            ->whereNull('max_reps_per_range')
            ->orWhere('max_reps_per_range', '<', 1)
            ->update(['max_reps_per_range' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
            ->where('max_reps_per_hour', '<>', 2)
            ->update(['max_reps_per_hour' => 2]);

        if (Schema::hasColumn('doctor_availabilities', 'max_reps_per_range')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->dropColumn('max_reps_per_range');
            });
        }
    }

    private function calculateDurationMinutes(string $startTime, string $endTime, bool $endsNextDay): int
    {
        $anchorDate = Carbon::create(2026, 1, 4, 0, 0, 0);

        $startAt = $this->buildTimeOnAnchor($anchorDate, $startTime);
        $endAt = $this->buildTimeOnAnchor($anchorDate, $endTime);
        if ($startAt === null || $endAt === null) {
            return 60;
        }

        $isOvernight = $endsNextDay || $endAt->lessThanOrEqualTo($startAt);
        if ($isOvernight) {
            $endAt->addDay();
        }

        $minutes = $startAt->diffInMinutes($endAt, false);
        if ($minutes <= 0) {
            return 1;
        }

        return $minutes;
    }

    private function buildTimeOnAnchor(Carbon $anchorDate, string $time): ?Carbon
    {
        $trimmedTime = trim($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) !== 1) {
            return null;
        }

        return $anchorDate->copy()->setTime(
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3]
        );
    }
};
