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

        if (!Schema::hasColumn('doctor_availabilities', 'ends_next_day')) {
            Schema::table('doctor_availabilities', function (Blueprint $table) {
                $table->boolean('ends_next_day')->default(false)->after('end_time');
            });
        }

        DB::table('doctor_availabilities')
            ->whereColumn('end_time', '<', 'start_time')
            ->update(['ends_next_day' => true]);

        DB::table('doctor_availabilities')
            ->select('id', 'date')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $normalizedWeekday = $this->normalizeAvailabilityWeekday((string) $row->date);
                    if ($normalizedWeekday === null) {
                        continue;
                    }

                    if ($normalizedWeekday !== (string) $row->date) {
                        DB::table('doctor_availabilities')
                            ->where('id', $row->id)
                            ->update(['date' => $normalizedWeekday]);
                    }
                }
            });

        $availableRows = DB::table('doctor_availabilities')
            ->where('status', 'available')
            ->orderBy('doctors_id')
            ->orderBy('date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'doctors_id', 'date']);

        $seenDoctorWeekdayKeys = [];
        $duplicateAvailabilityIds = [];

        foreach ($availableRows as $row) {
            $normalizedWeekday = $this->normalizeAvailabilityWeekday((string) $row->date);
            if ($normalizedWeekday === null) {
                continue;
            }

            $doctorWeekdayKey = $row->doctors_id . '|' . $normalizedWeekday;
            if (isset($seenDoctorWeekdayKeys[$doctorWeekdayKey])) {
                $duplicateAvailabilityIds[] = $row->id;
                continue;
            }

            $seenDoctorWeekdayKeys[$doctorWeekdayKey] = true;
        }

        foreach (array_chunk($duplicateAvailabilityIds, 500) as $idsChunk) {
            DB::table('doctor_availabilities')
                ->whereIn('id', $idsChunk)
                ->update(['status' => 'canceled']);
        }
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

    private function normalizeAvailabilityWeekday(string $value): ?string
    {
        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return null;
        }

        $normalizedWeekday = strtolower($trimmedValue);
        $weekdays = [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];

        if (in_array($normalizedWeekday, $weekdays, true)) {
            return $normalizedWeekday;
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $trimmedValue);
        } catch (\Exception $exception) {
            return null;
        }

        if ($parsedDate->format('Y-m-d') !== $trimmedValue) {
            return null;
        }

        return strtolower($parsedDate->format('l'));
    }
};
