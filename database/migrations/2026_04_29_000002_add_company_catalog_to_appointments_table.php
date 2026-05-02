<?php

use App\Models\RepCompanyCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'company_catalog_id')) {
                $table->foreignIdFor(RepCompanyCatalog::class, 'company_catalog_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('rep_company_catalogs')
                    ->nullOnDelete();
            }
        });

        $this->makeCompanyIdNullable();
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'company_catalog_id')) {
                $table->dropConstrainedForeignId('company_catalog_id');
            }
        });
    }

    private function makeCompanyIdNullable(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE appointments DROP FOREIGN KEY appointments_company_id_foreign');
            } catch (\Throwable $exception) {
                // The constraint may already be absent in test or drifted databases.
            }
            DB::statement('ALTER TABLE appointments MODIFY company_id BIGINT UNSIGNED NULL');
            try {
                DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE');
            } catch (\Throwable $exception) {
                // Keep the migration focused on nullability if the constraint already exists.
            }
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE appointments ALTER COLUMN company_id DROP NOT NULL');
        }
    }
};
