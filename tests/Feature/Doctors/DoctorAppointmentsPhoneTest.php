<?php

namespace Tests\Feature\Doctors;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\Specialty;
use Carbon\Carbon;
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

    public function test_doctor_appointments_endpoint_rejects_invalid_pagination_values(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $invalidPageResponse = $this->getJson('/api/doctor/doctor/appointments?page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidPerPageResponse = $this->getJson('/api/doctor/doctor/appointments?per_page=101');
        $invalidPerPageResponse->assertStatus(422);
        $invalidPerPageResponse->assertJsonPath('code', 422);
    }

    public function test_doctor_appointments_endpoint_returns_pagination_metadata_and_honors_page_and_per_page(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('12:0%d:00', $offset),
                'end_time' => sprintf('12:0%d:00', $offset + 1),
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 4);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_doctor_appointments_endpoint_combines_status_filter_with_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('14:0%d:00', $offset),
                'end_time' => sprintf('14:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=confirmed&per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertJsonPath('data.1.status', 'confirmed');
    }

    public function test_doctor_appointments_endpoint_returns_not_found_with_pagination_when_filter_is_empty(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=left');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Appointments Not Found');
        $response->assertJsonPath('pagination.total', 0);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonCount(0, 'data');
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

    public function test_reps_booked_appointments_refreshes_pending_to_suspended_without_cron(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subMinutes(20);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'pending',
            'cancelled_by' => null,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id', $appointment->id);
        $response->assertJsonPath('data.0.status', 'suspended');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);
    }

    public function test_reps_booked_appointments_endpoint_filters_by_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'pending');
        $response->assertJsonPath('data.0.phone', $doctor->phone);
    }

    public function test_reps_booked_appointments_endpoint_rejects_invalid_pagination_values(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $invalidPageResponse = $this->getJson('/api/reps/booked/appointments?page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidPerPageResponse = $this->getJson('/api/reps/booked/appointments?per_page=0');
        $invalidPerPageResponse->assertStatus(422);
        $invalidPerPageResponse->assertJsonPath('code', 422);
    }

    public function test_reps_booked_appointments_endpoint_returns_pagination_metadata_and_honors_page_and_per_page(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('13:0%d:00', $offset),
                'end_time' => sprintf('13:0%d:00', $offset + 1),
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 4);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_reps_booked_appointments_endpoint_combines_status_filter_with_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('15:0%d:00', $offset),
                'end_time' => sprintf('15:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=confirmed&per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertJsonPath('data.1.status', 'confirmed');
    }

    public function test_reps_booked_appointments_endpoint_returns_not_found_with_pagination_when_filter_is_empty(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=left');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Appointments Not Found');
        $response->assertJsonPath('pagination.total', 0);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonCount(0, 'data');
    }

    public function test_doctor_appointments_refreshes_suspended_to_left_without_cron(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('suspended');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subHours(49);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => null,
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id', $appointment->id);
        $response->assertJsonPath('data.0.status', 'left');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'left',
            'cancelled_by' => 'system',
        ]);
    }

    public function test_reps_lefting_endpoint_returns_left_appointments_only(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('left');
        $leftAppointment = Appointment::firstOrFail();

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/appointments/lefting');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $leftAppointment->id);
        $response->assertJsonPath('data.0.status', 'left');
    }

    public function test_doctor_cancelled_endpoint_does_not_leak_other_doctors_appointments(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('cancelled');
        $ownAppointment = Appointment::firstOrFail();

        $otherSpecialty = Specialty::create(['name' => 'Neuro']);

        $otherDoctor = Doctors::create([
            'name' => 'Doctor B',
            'email' => 'doctor-b@example.com',
            'phone' => '01111111112',
            'password' => 'secret123',
            'specialty_id' => $otherSpecialty->id,
            'status' => 'active',
        ]);

        $otherRep = Representative::create([
            'name' => 'Rep B',
            'email' => 'rep-b@example.com',
            'phone' => '01033333334',
            'password' => 'secret123',
            'company_id' => $rep->company_id,
            'status' => 'active',
        ]);

        $otherAppointment = Appointment::create([
            'doctors_id' => $otherDoctor->id,
            'representative_id' => $otherRep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/appointments/cancelled');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $doctorIds = collect($response->json('data'))->pluck('doctor.id')->unique()->values()->all();

        $this->assertContains($ownAppointment->id, $ids);
        $this->assertNotContains($otherAppointment->id, $ids);
        $this->assertSame([$doctor->id], $doctorIds);
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
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
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
