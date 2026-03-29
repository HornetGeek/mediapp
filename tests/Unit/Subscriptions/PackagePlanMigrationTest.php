<?php

namespace Tests\Unit\Subscriptions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackagePlanMigrationTest extends TestCase
{
    public function test_migration_backfills_plan_type_and_billing_months_from_existing_duration_values(): void
    {
        Schema::dropIfExists('packages');

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('duration');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('packages')->insert([
            ['name' => 'Q Plan', 'price' => 100, 'duration' => 90, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'S Plan', 'price' => 200, 'duration' => 180, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'A Plan', 'price' => 300, 'duration' => 365, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Legacy Plan', 'price' => 400, 'duration' => 45, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require base_path('database/migrations/2026_03_29_180000_add_plan_type_and_billing_months_to_packages_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('packages', 'plan_type'));
        $this->assertTrue(Schema::hasColumn('packages', 'billing_months'));

        $mapped = DB::table('packages')
            ->select(['name', 'plan_type', 'billing_months'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->name => ['plan_type' => $row->plan_type, 'billing_months' => $row->billing_months]])
            ->toArray();

        $this->assertSame('quarterly', $mapped['Q Plan']['plan_type']);
        $this->assertSame(3, (int) $mapped['Q Plan']['billing_months']);

        $this->assertSame('semi_annual', $mapped['S Plan']['plan_type']);
        $this->assertSame(6, (int) $mapped['S Plan']['billing_months']);

        $this->assertSame('annual', $mapped['A Plan']['plan_type']);
        $this->assertSame(12, (int) $mapped['A Plan']['billing_months']);

        $this->assertSame('custom_days', $mapped['Legacy Plan']['plan_type']);
        $this->assertNull($mapped['Legacy Plan']['billing_months']);
    }
}
