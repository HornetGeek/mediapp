<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments') || !Schema::hasColumn('appointments', 'status')) {
            return;
        }

        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $statusColumn = DB::selectOne("SHOW COLUMNS FROM appointments LIKE 'status'");
        $columnType = strtolower((string) ($statusColumn->Type ?? ''));

        if (str_contains($columnType, "'deleted'")) {
            return;
        }

        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN status ENUM('cancelled','confirmed','pending','left','suspended','deleted') NULL"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments') || !Schema::hasColumn('appointments', 'status')) {
            return;
        }

        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('appointments')
            ->where('status', 'deleted')
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN status ENUM('cancelled','confirmed','pending','left','suspended') NULL"
        );
    }
};
