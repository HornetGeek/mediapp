<?php

namespace App\Console\Commands;

use App\Models\DoctorAvailability;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDoctorAvailabilityStatus extends Command
{
    protected $signature = 'doctor:availability-status {--repair : Restore latest slot per weekday to available for doctors with zero active availability rows}';

    protected $description = 'Audit and optionally repair doctors that have availability history but no rows with status=available';

    public function handle(): int
    {
        $doctorSummaries = DoctorAvailability::query()
            ->select(
                'doctors_id',
                DB::raw('COUNT(*) as total_rows'),
                DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_rows")
            )
            ->groupBy('doctors_id')
            ->orderBy('doctors_id')
            ->get();

        $affectedDoctors = $doctorSummaries
            ->filter(function ($summary) {
                return (int) $summary->total_rows > 0 && (int) $summary->available_rows === 0;
            })
            ->values();

        if ($affectedDoctors->isEmpty()) {
            $this->info('No doctors found with availability history and zero active availability rows.');
            return self::SUCCESS;
        }

        $tableRows = [];
        $restoreMap = [];

        foreach ($affectedDoctors as $summary) {
            $restoreIds = $this->findLatestAvailabilityIdsByWeekday((int) $summary->doctors_id);
            $restoreMap[(int) $summary->doctors_id] = $restoreIds;

            $tableRows[] = [
                (int) $summary->doctors_id,
                (int) $summary->total_rows,
                (int) $summary->available_rows,
                count($restoreIds),
            ];
        }

        $this->table(
            ['Doctor ID', 'Total Rows', 'Available Rows', 'Rows To Restore'],
            $tableRows
        );

        if (!$this->option('repair')) {
            $this->warn('Dry run only. Re-run with --repair to restore rows.');
            return self::SUCCESS;
        }

        $restoredRows = 0;

        DB::transaction(function () use ($restoreMap, &$restoredRows) {
            foreach ($restoreMap as $doctorId => $restoreIds) {
                if (empty($restoreIds)) {
                    continue;
                }

                $restoredRows += DoctorAvailability::query()
                    ->where('doctors_id', $doctorId)
                    ->whereIn('id', $restoreIds)
                    ->update([
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info("Repair completed. Restored {$restoredRows} availability row(s).");

        return self::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function findLatestAvailabilityIdsByWeekday(int $doctorId): array
    {
        $availabilities = DoctorAvailability::query()
            ->where('doctors_id', $doctorId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'date', 'updated_at']);

        $latestByWeekday = [];

        foreach ($availabilities as $availability) {
            $weekday = $this->normalizeWeekday((string) $availability->date);
            if ($weekday === null || isset($latestByWeekday[$weekday])) {
                continue;
            }

            $latestByWeekday[$weekday] = (int) $availability->id;
        }

        return array_values($latestByWeekday);
    }

    private function normalizeWeekday(string $value): ?string
    {
        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return null;
        }

        $weekday = strtolower($trimmedValue);
        $validWeekdays = [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];

        if (in_array($weekday, $validWeekdays, true)) {
            return $weekday;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $trimmedValue);
        } catch (\Exception $exception) {
            return null;
        }

        if ($date->format('Y-m-d') !== $trimmedValue) {
            return null;
        }

        return strtolower($date->format('l'));
    }
}
