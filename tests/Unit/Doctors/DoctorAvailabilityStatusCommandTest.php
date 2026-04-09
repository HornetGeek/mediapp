<?php

namespace Tests\Unit\Doctors;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorAvailabilityStatusCommandTest extends TestCase
{
    public function test_command_audits_doctors_with_zero_available_rows_without_mutating_data(): void
    {
        $this->createDoctorAvailabilitiesTable();
        $this->seedAvailabilityRows();

        Artisan::call('doctor:availability-status');
        $output = Artisan::output();

        $this->assertStringContainsString('Dry run only. Re-run with --repair to restore rows.', $output);
        $this->assertStringContainsString('1', $output);

        $this->assertDatabaseHas('doctor_availabilities', ['id' => 1, 'status' => 'canceled']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 2, 'status' => 'booked']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 3, 'status' => 'busy']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 5, 'status' => 'canceled']);
    }

    public function test_command_repairs_affected_doctors_by_restoring_latest_row_per_slot_signature(): void
    {
        $this->createDoctorAvailabilitiesTable();
        $this->seedAvailabilityRows();

        Artisan::call('doctor:availability-status', ['--repair' => true]);

        $this->assertDatabaseHas('doctor_availabilities', ['id' => 1, 'status' => 'available']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 2, 'status' => 'available']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 3, 'status' => 'available']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 4, 'status' => 'available']);
        $this->assertDatabaseHas('doctor_availabilities', ['id' => 5, 'status' => 'canceled']);
    }

    private function createDoctorAvailabilitiesTable(): void
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
    }

    private function seedAvailabilityRows(): void
    {
        DB::table('doctor_availabilities')->insert([
            [
                'id' => 1,
                'doctors_id' => 1,
                'date' => 'monday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'ends_next_day' => 0,
                'status' => 'canceled',
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            [
                'id' => 2,
                'doctors_id' => 1,
                'date' => '2026-04-20',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'ends_next_day' => 0,
                'status' => 'booked',
                'created_at' => now()->subMinutes(4),
                'updated_at' => now()->subMinute(),
            ],
            [
                'id' => 5,
                'doctors_id' => 1,
                'date' => 'monday',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'ends_next_day' => 0,
                'status' => 'canceled',
                'created_at' => now()->subMinutes(6),
                'updated_at' => now()->subMinutes(6),
            ],
            [
                'id' => 3,
                'doctors_id' => 1,
                'date' => '2026-04-21',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'ends_next_day' => 0,
                'status' => 'busy',
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'id' => 4,
                'doctors_id' => 2,
                'date' => 'tuesday',
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'ends_next_day' => 0,
                'status' => 'available',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinute(),
            ],
        ]);
    }
}
