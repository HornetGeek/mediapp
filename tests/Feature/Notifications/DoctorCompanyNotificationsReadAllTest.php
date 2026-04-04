<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\Doctors;
use App\Models\Notification;
use App\Models\Representative;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorCompanyNotificationsReadAllTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    public function test_doctor_can_mark_all_unread_notifications_as_read(): void
    {
        $doctor = $this->createDoctor('doctor-one@example.com', '01010000001');
        $otherDoctor = $this->createDoctor('doctor-two@example.com', '01010000002');
        $company = $this->createCompany('company-one@example.com');

        Notification::create([
            'title' => 'Doctor unread 1',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'doctor',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);
        Notification::create([
            'title' => 'Doctor unread 2',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'doctor',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);
        Notification::create([
            'title' => 'Doctor already read',
            'body' => 'Body',
            'is_read' => true,
            'target_type' => 'doctor',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);
        Notification::create([
            'title' => 'Other doctor unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'doctor',
            'notifiable_id' => $otherDoctor->id,
            'notifiable_type' => Doctors::class,
        ]);
        Notification::create([
            'title' => 'Company unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'company',
            'notifiable_id' => $company->id,
            'notifiable_type' => Company::class,
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $firstCall = $this->putJson('/api/doctor/notifications/read');
        $firstCall->assertStatus(200);
        $firstCall->assertJsonPath('data.updated_count', 2);

        $secondCall = $this->putJson('/api/doctor/notifications/read');
        $secondCall->assertStatus(200);
        $secondCall->assertJsonPath('data.updated_count', 0);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Doctor unread 1',
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Doctor unread 2',
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Other doctor unread',
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Company unread',
            'is_read' => false,
        ]);
    }

    public function test_company_can_mark_all_unread_notifications_as_read(): void
    {
        $company = $this->createCompany('company-main@example.com');
        $otherCompany = $this->createCompany('company-other@example.com');
        $doctor = $this->createDoctor('doctor-for-company@example.com', '01020000001');

        Notification::create([
            'title' => 'Company unread 1',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'company',
            'notifiable_id' => $company->id,
            'notifiable_type' => Company::class,
        ]);
        Notification::create([
            'title' => 'Company unread 2',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'company',
            'notifiable_id' => $company->id,
            'notifiable_type' => Company::class,
        ]);
        Notification::create([
            'title' => 'Company already read',
            'body' => 'Body',
            'is_read' => true,
            'target_type' => 'company',
            'notifiable_id' => $company->id,
            'notifiable_type' => Company::class,
        ]);
        Notification::create([
            'title' => 'Other company unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'company',
            'notifiable_id' => $otherCompany->id,
            'notifiable_type' => Company::class,
        ]);
        Notification::create([
            'title' => 'Doctor unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'doctor',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Sanctum::actingAs($company, ['company']);

        $firstCall = $this->putJson('/api/company/notifications/read');
        $firstCall->assertStatus(200);
        $firstCall->assertJsonPath('data.updated_count', 2);

        $secondCall = $this->putJson('/api/company/notifications/read');
        $secondCall->assertStatus(200);
        $secondCall->assertJsonPath('data.updated_count', 0);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Company unread 1',
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Company unread 2',
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Other company unread',
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Doctor unread',
            'is_read' => false,
        ]);
    }

    public function test_reps_mark_all_notifications_endpoint_still_works(): void
    {
        $company = $this->createCompany('company-regression@example.com');
        $rep = $this->createRepresentative($company, 'rep-regression@example.com', '01030000001');

        Notification::create([
            'title' => 'Rep unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/notifications/read');

        $response->assertStatus(200);
        $response->assertJsonPath('data.updated_count', 1);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Rep unread',
            'is_read' => true,
        ]);
    }

    public function test_doctor_and_company_mark_all_read_require_authentication(): void
    {
        $doctorResponse = $this->putJson('/api/doctor/notifications/read');
        $doctorResponse->assertStatus(401);

        $companyResponse = $this->putJson('/api/company/notifications/read');
        $companyResponse->assertStatus(401);
    }

    public function test_doctor_notifications_endpoint_logs_booked_duplicate_diagnostics_when_debug_enabled(): void
    {
        config(['notifications.debug' => true]);

        $doctor = $this->createDoctor('doctor-debug@example.com', '01010000003');

        Notification::create([
            'title' => 'New visit booked.',
            'body' => 'Visit A',
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => 'appointment:569:booked:to:doctor:' . $doctor->id,
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Notification::create([
            'title' => 'New visit booked.',
            'body' => 'Visit A',
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => 'appointment:569:booked:to:doctor:' . $doctor->id,
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Notification::create([
            'title' => 'New visit booked.',
            'body' => 'Visit A',
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => 'appointment:570:booked:to:doctor:' . $doctor->id,
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Notification::create([
            'title' => 'Other',
            'body' => 'Not booked',
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => 'appointment:999:doctor_cancelled:to:rep:1',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Log::spy();
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('code', 200);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($doctor) {
                return $message === 'Doctor notifications booked diagnostics'
                    && ($context['doctor_id'] ?? null) === $doctor->id
                    && ($context['returned_count'] ?? null) === 4
                    && ($context['booked_count'] ?? null) === 3
                    && count($context['duplicate_key_groups'] ?? []) === 1
                    && count($context['duplicate_semantic_groups'] ?? []) === 1;
            })
            ->once();
    }

    private function createCompany(string $email): Company
    {
        return Company::create([
            'package_id' => 1,
            'name' => 'Company ' . uniqid(),
            'phone' => '012' . random_int(10000000, 99999999),
            'email' => $email,
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 5,
            'status' => 'active',
        ]);
    }

    private function createDoctor(string $email, string $phone): Doctors
    {
        return Doctors::create([
            'name' => 'Doctor ' . uniqid(),
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'status' => 'active',
        ]);
    }

    private function createRepresentative(Company $company, string $email, string $phone): Representative
    {
        return Representative::create([
            'company_id' => $company->id,
            'name' => 'Representative ' . uniqid(),
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'status' => 'active',
        ]);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'notifications',
            'representatives',
            'doctors',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id')->nullable();
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

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->enum('target_type', ['doctor', 'reps', 'company'])->nullable();
            $table->string('dedupe_key')->nullable();
            $table->char('dedupe_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->timestamps();
            $table->index('dedupe_key', 'notifications_dedupe_key_index');
            $table->unique('dedupe_fingerprint', 'notifications_dedupe_fingerprint_unique');
        });
    }
}
