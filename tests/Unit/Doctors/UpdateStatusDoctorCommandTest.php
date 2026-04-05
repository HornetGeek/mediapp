<?php

namespace Tests\Unit\Doctors;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UpdateStatusDoctorCommandTest extends TestCase
{
    public function test_command_resets_expired_busy_doctors_and_clears_busy_dates(): void
    {
        $this->createDoctorsTable();

        $today = Carbon::now('Africa/Cairo')->toDateString();
        $yesterday = Carbon::now('Africa/Cairo')->subDay()->toDateString();
        $twoDaysAgo = Carbon::now('Africa/Cairo')->subDays(2)->toDateString();

        DB::table('doctors')->insert([
            [
                'id' => 1,
                'name' => 'Doctor Expired Busy',
                'email' => 'doctor-expired-busy@example.com',
                'password' => 'secret123',
                'status' => 'busy',
                'from_date' => $twoDaysAgo,
                'to_date' => $yesterday,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Doctor Current Busy',
                'email' => 'doctor-current-busy@example.com',
                'password' => 'secret123',
                'status' => 'busy',
                'from_date' => $today,
                'to_date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Artisan::call('app:update-status-doctor');

        $this->assertDatabaseHas('doctors', [
            'id' => 1,
            'status' => 'active',
            'from_date' => null,
            'to_date' => null,
        ]);

        $this->assertDatabaseHas('doctors', [
            'id' => 2,
            'status' => 'busy',
            'from_date' => $today,
            'to_date' => $today,
        ]);
    }

    private function createDoctorsTable(): void
    {
        Schema::dropIfExists('doctors');

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->timestamps();
        });
    }
}
