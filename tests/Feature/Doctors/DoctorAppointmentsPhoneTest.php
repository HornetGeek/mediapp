<?php

namespace Tests\Feature\Doctors;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\Specialty;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorAppointmentsPhoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    public function test_doctor_appointments_endpoint_returns_representative_phone(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this
            ->getJson('/api/doctor/doctor/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.phone', $rep->phone);
        $response->assertJsonMissing(['phone' => $doctor->phone]);
    }

    public function test_doctor_appointments_endpoint_filters_by_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'pending');
        $response->assertJsonPath('data.0.phone', $rep->phone);
    }

    public function test_doctor_appointments_endpoint_rejects_invalid_status_value(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=invalid_status');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
    }

    public function test_doctor_status_endpoints_return_representative_phone_and_reps_endpoint_stays_unchanged(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $pendingResponse = $this
            ->getJson('/api/doctor/appointments/pending');
        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('data.0.phone', $rep->phone);

        $appointment = Appointment::firstOrFail();
        $appointment->update(['status' => 'cancelled']);

        $cancelledResponse = $this
            ->getJson('/api/doctor/appointments/cancelled');
        $cancelledResponse->assertStatus(200);
        $cancelledResponse->assertJsonPath('data.0.phone', $rep->phone);

        $appointment->update(['status' => 'confirmed']);
        $confirmedResponse = $this
            ->getJson('/api/doctor/appointments/confirmed');
        $confirmedResponse->assertStatus(200);
        $confirmedResponse->assertJsonPath('data.0.phone', $rep->phone);

        Sanctum::actingAs($rep, ['representative']);
        $repsResponse = $this
            ->getJson('/api/reps/booked/appointments');
        $repsResponse->assertStatus(200);
        $repsResponse->assertJsonPath('data.0.phone', $doctor->phone);
    }

    /**
     * @return array{0: \App\Models\Doctors, 1: \App\Models\Representative}
     */
    private function seedDoctorAppointmentData(string $status): array
    {
        $specialty = Specialty::create(['name' => 'Cardio']);

        $doctor = Doctors::create([
            'name' => 'Doctor A',
            'email' => 'doctor-a@example.com',
            'phone' => '01111111111',
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1000,
            'duration' => 90,
            'plan_type' => 'quarterly',
            'billing_months' => 3,
        ]);

        $company = Company::create([
            'name' => 'Company A',
            'package_id' => $package->id,
            'phone' => '01222222222',
            'email' => 'company-a@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $rep = Representative::create([
            'name' => 'Rep A',
            'email' => 'rep-a@example.com',
            'phone' => '01033333333',
            'password' => 'secret123',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => $status,
        ]);

        return [$doctor, $rep];
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'appointments',
            'representatives',
            'companies',
            'packages',
            'doctors',
            'specialties',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('duration');
            $table->string('plan_type')->default('custom_days');
            $table->unsignedTinyInteger('billing_months')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('visits_per_day')->nullable();
            $table->integer('num_of_reps')->nullable();
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('fcm_token')->nullable();
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
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
