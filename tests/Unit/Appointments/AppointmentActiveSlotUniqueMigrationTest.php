<?php

namespace Tests\Unit\Appointments;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentActiveSlotUniqueMigrationTest extends TestCase
{
    public function test_migration_removes_active_slot_lock_and_adds_availability_reference(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_availabilities');

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedInteger('max_reps_per_range')->nullable();
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->nullable();
            $table->unsignedTinyInteger('slot_lock')->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
            $table->unique(['doctors_id', 'date', 'start_time', 'slot_lock'], 'appointments_active_slot_unique');
        });

        $migration = require base_path('database/migrations/2026_05_24_000000_add_doctor_availability_id_to_appointments_and_remove_active_slot_lock.php');
        $migration->up();

        if (DB::getDriverName() === 'sqlite') {
            $this->assertTrue(Schema::hasColumn('appointments', 'slot_lock'));
        } else {
            $this->assertFalse(Schema::hasColumn('appointments', 'slot_lock'));
        }
        $this->assertTrue(Schema::hasColumn('appointments', 'doctor_availability_id'));

        $indexes = collect(DB::select("PRAGMA index_list('appointments')"))
            ->pluck('name')
            ->all();
        $this->assertNotContains('appointments_active_slot_unique', $indexes);
    }

    public function test_duplicate_active_rows_for_same_range_are_allowed_after_migration(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctor_availabilities');

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->nullable();
            $table->unsignedTinyInteger('slot_lock')->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
            $table->unique(['doctors_id', 'date', 'start_time', 'slot_lock'], 'appointments_active_slot_unique');
        });

        $migration = require base_path('database/migrations/2026_05_24_000000_add_doctor_availability_id_to_appointments_and_remove_active_slot_lock.php');
        $migration->up();

        DB::table('doctor_availabilities')->insert([
            'id' => 1,
            'doctors_id' => 99,
            'date' => 'wednesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('appointments')->insert([
            [
                'doctors_id' => 99,
                'representative_id' => 201,
                'company_id' => 301,
                'doctor_availability_id' => 1,
                'date' => '2026-04-01',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'pending',
                'appointment_code' => '20000000-0000-4000-8000-000000000001',
                'cancelled_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'doctors_id' => 99,
                'representative_id' => 202,
                'company_id' => 302,
                'doctor_availability_id' => 1,
                'date' => '2026-04-01',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'confirmed',
                'appointment_code' => '20000000-0000-4000-8000-000000000002',
                'cancelled_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(2, DB::table('appointments')->whereIn('status', ['pending', 'confirmed'])->count());
    }
}
