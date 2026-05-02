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
        if (!Schema::hasTable('representatives')) {
            return;
        }

        Schema::table('representatives', function (Blueprint $table) {
            if (!Schema::hasColumn('representatives', 'company_catalog_id')) {
                $table->foreignIdFor(RepCompanyCatalog::class, 'company_catalog_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('rep_company_catalogs')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('representatives', 'requested_company_name')) {
                $table->string('requested_company_name')->nullable()->after('company_catalog_id');
            }

            if (!Schema::hasColumn('representatives', 'registration_status')) {
                $table->enum('registration_status', ['active', 'pending', 'rejected'])
                    ->default('active')
                    ->after('status');
            }

            if (!Schema::hasColumn('representatives', 'daily_visits_limit')) {
                $table->unsignedInteger('daily_visits_limit')->nullable()->after('registration_status');
            }
        });

        $this->makeCompanyIdNullable();
    }

    public function down(): void
    {
        if (!Schema::hasTable('representatives')) {
            return;
        }

        Schema::table('representatives', function (Blueprint $table) {
            if (Schema::hasColumn('representatives', 'company_catalog_id')) {
                $table->dropConstrainedForeignId('company_catalog_id');
            }

            foreach (['requested_company_name', 'registration_status', 'daily_visits_limit'] as $column) {
                if (Schema::hasColumn('representatives', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeCompanyIdNullable(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE representatives DROP FOREIGN KEY representatives_company_id_foreign');
            } catch (\Throwable $exception) {
                // The constraint may already be absent in test or drifted databases.
            }
            DB::statement('ALTER TABLE representatives MODIFY company_id BIGINT UNSIGNED NULL');
            try {
                DB::statement('ALTER TABLE representatives ADD CONSTRAINT representatives_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE');
            } catch (\Throwable $exception) {
                // Keep the migration focused on nullability if the constraint already exists.
            }
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE representatives ALTER COLUMN company_id DROP NOT NULL');
        }
    }
};
