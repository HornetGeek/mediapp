<?php

namespace Tests\Unit\Appointments;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentActiveSlotUniqueMigrationTest extends TestCase
{
    public function test_migration_cleans_duplicate_active_slots_and_adds_unique_constraint(): void
    {
        Schema::dropIfExists('appointments');

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });

        DB::table('appointments')->insert([
            [
                'id' => 1,
                'doctors_id' => 1,
                'representative_id' => 11,
                'company_id' => 101,
                'date' => '2026-03-30',
                'start_time' => '10:00:00',
                'end_time' => '10:05:00',
                'status' => 'pending',
                'appointment_code' => '10000000-0000-4000-8000-000000000001',
                'cancelled_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'doctors_id' => 1,
                'representative_id' => 12,
                'company_id' => 102,
                'date' => '2026-03-30',
                'start_time' => '10:00:00',
                'end_time' => '10:05:00',
                'status' => 'confirmed',
                'appointment_code' => '10000000-0000-4000-8000-000000000002',
                'cancelled_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'doctors_id' => 1,
                'representative_id' => 13,
                'company_id' => 103,
                'date' => '2026-03-30',
                'start_time' => '10:00:00',
                'end_time' => '10:05:00',
                'status' => 'pending',
                'appointment_code' => '10000000-0000-4000-8000-000000000003',
                'cancelled_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require base_path('database/migrations/2026_03_30_130000_enforce_active_slot_uniqueness_on_appointments_table.php');
        $migration->up();

        $activeRowsCount = DB::table('appointments')
            ->where('doctors_id', 1)
            ->where('date', '2026-03-30')
            ->where('start_time', '10:00:00')
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $this->assertSame(1, $activeRowsCount);
        $this->assertDatabaseHas('appointments', [
            'id' => 1,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => 2,
            'status' => 'cancelled',
            'cancelled_by' => 'system:duplicate-slot-cleanup',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => 3,
            'status' => 'cancelled',
            'cancelled_by' => 'system:duplicate-slot-cleanup',
        ]);

        $this->assertTrue(Schema::hasColumn('appointments', 'slot_lock'));

        $indexes = collect(DB::select("PRAGMA index_list('appointments')"))
            ->pluck('name')
            ->all();
        $this->assertContains('appointments_active_slot_unique', $indexes);
    }

    public function test_unique_index_blocks_second_active_row_for_same_slot(): void
    {
        Schema::dropIfExists('appointments');

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });

        $migration = require base_path('database/migrations/2026_03_30_130000_enforce_active_slot_uniqueness_on_appointments_table.php');
        $migration->up();

        $first = [
            'doctors_id' => 99,
            'representative_id' => 201,
            'company_id' => 301,
            'date' => '2026-04-01',
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'pending',
            'appointment_code' => '20000000-0000-4000-8000-000000000001',
            'cancelled_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $second = [
            'doctors_id' => 99,
            'representative_id' => 202,
            'company_id' => 302,
            'date' => '2026-04-01',
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'confirmed',
            'appointment_code' => '20000000-0000-4000-8000-000000000002',
            'cancelled_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (DB::getDriverName() === 'sqlite') {
            $first['slot_lock'] = 1;
            $second['slot_lock'] = 1;
        }

        DB::table('appointments')->insert($first);

        $this->expectException(QueryException::class);
        DB::table('appointments')->insert($second);
    }
}

