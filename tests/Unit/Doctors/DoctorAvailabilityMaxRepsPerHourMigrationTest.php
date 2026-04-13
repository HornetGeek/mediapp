<?php

namespace Tests\Unit\Doctors;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorAvailabilityMaxRepsPerHourMigrationTest extends TestCase
{
    public function test_migration_adds_column_with_default_and_backfills_existing_rows(): void
    {
        Schema::dropIfExists('doctor_availabilities');

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        DB::table('doctor_availabilities')->insert([
            [
                'id' => 1,
                'doctors_id' => 10,
                'date' => 'monday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'ends_next_day' => false,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'doctors_id' => 11,
                'date' => 'tuesday',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'ends_next_day' => false,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require base_path('database/migrations/2026_04_13_000000_add_max_reps_per_hour_to_doctor_availabilities_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('doctor_availabilities', 'max_reps_per_hour'));
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 1,
            'max_reps_per_hour' => 2,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 2,
            'max_reps_per_hour' => 2,
        ]);

        DB::table('doctor_availabilities')
            ->where('id', 1)
            ->update(['max_reps_per_hour' => 1]);
        DB::table('doctor_availabilities')
            ->where('id', 2)
            ->update(['max_reps_per_hour' => 3]);

        $migration->up();

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 1,
            'max_reps_per_hour' => 1,
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 2,
            'max_reps_per_hour' => 2,
        ]);
    }
}
