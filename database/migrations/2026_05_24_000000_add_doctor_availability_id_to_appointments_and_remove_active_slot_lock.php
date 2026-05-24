<?php

use App\Models\DoctorAvailability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_INDEX_NAME = 'appointments_active_slot_unique';

    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        $this->dropOldActiveSlotUniqueIndex();
        $this->dropOldSlotLockColumn();
        $this->addDoctorAvailabilityReference();
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        if (Schema::hasColumn('appointments', 'doctor_availability_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('doctor_availability_id');
            });
        }
    }

    private function addDoctorAvailabilityReference(): void
    {
        if (!Schema::hasTable('doctor_availabilities')
            || Schema::hasColumn('appointments', 'doctor_availability_id')
        ) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignIdFor(DoctorAvailability::class, 'doctor_availability_id')
                ->nullable()
                ->constrained('doctor_availabilities')
                ->nullOnDelete();
        });
    }

    private function dropOldActiveSlotUniqueIndex(): void
    {
        if (!$this->hasIndex(self::OLD_INDEX_NAME)) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(self::OLD_INDEX_NAME);
        });
    }

    private function dropOldSlotLockColumn(): void
    {
        if (!Schema::hasColumn('appointments', 'slot_lock')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('slot_lock');
        });
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
