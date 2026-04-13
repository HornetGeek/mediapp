<?php

namespace Tests\Unit\Doctors;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorAvailabilityMaxRepsPerHourMigrationTest extends TestCase
{
    public function test_migration_replaces_hourly_column_with_range_column_and_backfills_values(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('This migration test requires a database that supports dropping columns directly.');
        }

        Schema::dropIfExists('doctor_availabilities');

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedTinyInteger('max_reps_per_hour')->default(2);
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        DB::table('doctor_availabilities')->insert([
            [
                'id' => 1,
                'doctors_id' => 10,
                'date' => 'monday',
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'ends_next_day' => false,
                'max_reps_per_hour' => 2,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'doctors_id' => 11,
                'date' => 'tuesday',
                'start_time' => '10:00:00',
                'end_time' => '13:00:00',
                'ends_next_day' => false,
                'max_reps_per_hour' => 1,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'doctors_id' => 12,
                'date' => 'wednesday',
                'start_time' => '22:00:00',
                'end_time' => '02:00:00',
                'ends_next_day' => true,
                'max_reps_per_hour' => 2,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'doctors_id' => 13,
                'date' => 'thursday',
                'start_time' => '09:00:00',
                'end_time' => '09:20:00',
                'ends_next_day' => false,
                'max_reps_per_hour' => 1,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'doctors_id' => 14,
                'date' => 'friday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'ends_next_day' => false,
                'max_reps_per_hour' => 9,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require base_path('database/migrations/2026_04_14_000000_replace_max_reps_per_hour_with_max_reps_per_range_on_doctor_availabilities_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('doctor_availabilities', 'max_reps_per_range'));
        $this->assertFalse(Schema::hasColumn('doctor_availabilities', 'max_reps_per_hour'));

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 1,
            'max_reps_per_range' => 6,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 2,
            'max_reps_per_range' => 3,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 3,
            'max_reps_per_range' => 8,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 4,
            'max_reps_per_range' => 1,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 5,
            'max_reps_per_range' => 2,
        ]);
    }
}
