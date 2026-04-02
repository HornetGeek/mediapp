<?php

namespace Tests\Unit\Doctors;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorAvailabilityWeekdayMigrationTest extends TestCase
{
    public function test_migration_backfills_weekdays_sets_ends_next_day_and_cancels_duplicate_weekday_slots(): void
    {
        Schema::dropIfExists('doctor_availabilities');

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        DB::table('doctor_availabilities')->insert([
            [
                'id' => 1,
                'doctors_id' => 1,
                'date' => '2026-04-20',
                'start_time' => '22:00:00',
                'end_time' => '02:00:00',
                'status' => 'available',
                'created_at' => now()->subMinutes(15),
                'updated_at' => now()->subMinutes(1),
            ],
            [
                'id' => 2,
                'doctors_id' => 1,
                'date' => 'Monday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'available',
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'id' => 3,
                'doctors_id' => 1,
                'date' => 'monday',
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'status' => 'available',
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(10),
            ],
            [
                'id' => 4,
                'doctors_id' => 1,
                'date' => '2026-04-21',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'available',
                'created_at' => now()->subMinutes(12),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'id' => 5,
                'doctors_id' => 2,
                'date' => '2026-04-20',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'status' => 'available',
                'created_at' => now()->subMinutes(8),
                'updated_at' => now()->subMinutes(3),
            ],
        ]);

        $migration = require base_path('database/migrations/2026_04_03_000000_add_ends_next_day_to_doctor_availabilities_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('doctor_availabilities', 'ends_next_day'));

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 1,
            'date' => 'monday',
            'ends_next_day' => 1,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 4,
            'date' => 'tuesday',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 5,
            'date' => 'monday',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 2,
            'status' => 'canceled',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => 3,
            'status' => 'canceled',
        ]);

        $doctorOneMondayAvailableCount = DB::table('doctor_availabilities')
            ->where('doctors_id', 1)
            ->where('date', 'monday')
            ->where('status', 'available')
            ->count();

        $this->assertSame(1, $doctorOneMondayAvailableCount);
    }
}
