<?php

namespace Tests\Unit\AppVersions;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppVersionPlatformMigrationTest extends TestCase
{
    public function test_migration_adds_platform_backfills_existing_data_and_enforces_unique_app_platform(): void
    {
        Schema::dropIfExists('app_versions');

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app_type');
            $table->string('version');
            $table->boolean('is_forced')->default(false);
            $table->string('store_url')->nullable();
            $table->timestamps();
        });

        DB::table('app_versions')->insert([
            [
                'app_type' => 'company',
                'version' => '1.0.0',
                'is_forced' => 0,
                'store_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'app_type' => 'company',
                'version' => '1.1.0',
                'is_forced' => 1,
                'store_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'app_type' => 'doctor',
                'version' => '2.0.0',
                'is_forced' => 0,
                'store_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require base_path('database/migrations/2026_03_29_210000_add_platform_to_app_versions_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('app_versions', 'platform'));

        $this->assertSame(1, DB::table('app_versions')->where('app_type', 'company')->count());
        $this->assertSame(1, DB::table('app_versions')->where('app_type', 'doctor')->count());

        $company = DB::table('app_versions')->where('app_type', 'company')->first();
        $doctor = DB::table('app_versions')->where('app_type', 'doctor')->first();

        $this->assertSame('both', $company->platform);
        $this->assertSame('both', $doctor->platform);
        $this->assertSame('1.1.0', $company->version);

        $this->expectException(QueryException::class);
        DB::table('app_versions')->insert([
            'app_type' => 'company',
            'platform' => 'both',
            'version' => '9.9.9',
            'is_forced' => 0,
            'store_url' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
