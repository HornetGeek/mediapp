<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'appointments_active_slot_unique';
    private const DUPLICATE_MARKER = 'system:duplicate-slot-cleanup';

    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        $this->cancelDuplicateActiveSlots();
        $this->ensureSlotLockColumn();
        $this->syncSlotLockForNonGeneratedDrivers();
        $this->ensureActiveSlotUniqueIndex();
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        if ($this->hasIndex(self::INDEX_NAME)) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }

        if (Schema::hasColumn('appointments', 'slot_lock')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropColumn('slot_lock');
            });
        }
    }

    private function cancelDuplicateActiveSlots(): void
    {
        $duplicateGroups = DB::table('appointments')
            ->select([
                'doctors_id',
                'date',
                'start_time',
                DB::raw('MIN(id) as keep_id'),
                DB::raw('COUNT(*) as records_count'),
            ])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNotNull('doctors_id')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->groupBy('doctors_id', 'date', 'start_time')
            ->having('records_count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('appointments')
                ->where('doctors_id', $group->doctors_id)
                ->where('date', $group->date)
                ->where('start_time', $group->start_time)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('id', '<>', $group->keep_id)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_by' => self::DUPLICATE_MARKER,
                    'updated_at' => now(),
                ]);
        }
    }

    private function ensureSlotLockColumn(): void
    {
        if (Schema::hasColumn('appointments', 'slot_lock')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE appointments
                 ADD COLUMN slot_lock TINYINT
                 GENERATED ALWAYS AS (
                    CASE WHEN status IN ('pending', 'confirmed') THEN 1 ELSE NULL END
                 ) STORED"
            );
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot_lock')->nullable()->after('status');
        });
    }

    private function syncSlotLockForNonGeneratedDrivers(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!Schema::hasColumn('appointments', 'slot_lock')) {
            return;
        }

        DB::table('appointments')
            ->whereIn('status', ['pending', 'confirmed'])
            ->update(['slot_lock' => 1]);

        DB::table('appointments')
            ->where(function ($query) {
                $query->whereNotIn('status', ['pending', 'confirmed'])
                    ->orWhereNull('status');
            })
            ->update(['slot_lock' => null]);
    }

    private function ensureActiveSlotUniqueIndex(): void
    {
        if ($this->hasIndex(self::INDEX_NAME)) {
            return;
        }

        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unique(['doctors_id', 'date', 'start_time', 'slot_lock'], self::INDEX_NAME);
            });
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, self::INDEX_NAME) || str_contains($message, 'duplicate')) {
                throw $exception;
            }

            throw $exception;
        }
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'appointments')
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = collect(DB::select("PRAGMA index_list('appointments')"))
                ->pluck('name')
                ->all();

            return in_array($indexName, $indexes, true);
        }

        return false;
    }
};

