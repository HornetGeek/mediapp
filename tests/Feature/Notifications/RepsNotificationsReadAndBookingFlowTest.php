<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\Notification;
use App\Models\Representative;
use App\Models\Specialty;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RepsNotificationsReadAndBookingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
        $this->registerSqliteConcatFunctionIfNeeded();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_representative_can_mark_all_unread_notifications_as_read(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'rep-one@example.com', '01011111111');
        $otherRep = $this->createRepresentative($company, 'rep-two@example.com', '01022222222');
        $doctor = $this->createDoctor('doctor-all-read@example.com', '01033333333');

        $firstUnread = Notification::create([
            'title' => 'First unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        $secondUnread = Notification::create([
            'title' => 'Second unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        Notification::create([
            'title' => 'Already read',
            'body' => 'Body',
            'is_read' => true,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        Notification::create([
            'title' => 'Other representative unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $otherRep->id,
            'notifiable_type' => Representative::class,
        ]);

        Notification::create([
            'title' => 'Doctor unread',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'doctor',
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/notifications/read');

        $response->assertStatus(200);
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('data.updated_count', 2);

        $this->assertDatabaseHas('notifications', [
            'id' => $firstUnread->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $secondUnread->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Other representative unread',
            'notifiable_id' => $otherRep->id,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'title' => 'Doctor unread',
            'notifiable_id' => $doctor->id,
            'is_read' => false,
        ]);
    }

    public function test_legacy_notification_read_endpoint_still_marks_single_notification(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'legacy-rep@example.com', '01044444444');

        $targetNotification = Notification::create([
            'title' => 'Legacy target',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        $otherNotification = Notification::create([
            'title' => 'Legacy untouched',
            'body' => 'Body',
            'is_read' => false,
            'target_type' => 'reps',
            'notifiable_id' => $rep->id,
            'notifiable_type' => Representative::class,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/notifications/' . $targetNotification->id . '/read');

        $response->assertStatus(200);
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('data.id', $targetNotification->id);
        $response->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $targetNotification->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $otherNotification->id,
            'is_read' => false,
        ]);
    }

    public function test_booking_appointment_creates_doctor_notification_and_attempts_fcm_send(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'booking-rep@example.com', '01055555555');
        $doctor = $this->createDoctor('booking-doctor@example.com', '01066666666', 'doctor-fcm-token');

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->once()
            ->with(
                $doctor->fcm_token,
                'New visit booked.',
                Mockery::type('string'),
                []
            )
            ->andReturn(['ok' => true]);

        $this->app->instance(FirebaseNotificationService::class, $service);

        Sanctum::actingAs($rep, ['representative']);

        $date = now()->toDateString();
        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:00:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('code', 201);

        $expectedDateTime = Carbon::parse($date . ' 10:00:00')->format('Y-m-d h:i a');
        $expectedBody = 'New visit booked with ' . $rep->name . ' at ' . $expectedDateTime;

        $this->assertDatabaseHas('appointments', [
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'title' => 'New visit booked.',
            'body' => $expectedBody,
            'is_read' => false,
            'target_type' => 'doctor',
        ]);
    }

    private function createCompany(): Company
    {
        return Company::create([
            'package_id' => 1,
            'name' => 'ACME Company',
            'phone' => '01200000000',
            'email' => 'company-' . uniqid() . '@example.com',
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 5,
            'status' => 'active',
        ]);
    }

    private function createRepresentative(Company $company, string $email, string $phone): Representative
    {
        return Representative::create([
            'company_id' => $company->id,
            'name' => 'Representative',
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'status' => 'active',
        ]);
    }

    private function createDoctor(string $email, string $phone, ?string $fcmToken = null): Doctors
    {
        $specialty = Specialty::create([
            'name' => 'Specialty ' . uniqid(),
        ]);

        return Doctors::create([
            'name' => 'Doctor',
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
            'fcm_token' => $fcmToken,
        ]);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'notifications',
            'doctor_blocks',
            'appointments',
            'representatives',
            'doctors',
            'specialties',
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

        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('blockable_id');
            $table->string('blockable_type');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->enum('target_type', ['doctor', 'reps', 'company'])->nullable();
            $table->string('dedupe_key')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->timestamps();
            $table->index('dedupe_key', 'notifications_dedupe_key_index');
        });
    }

    private function registerSqliteConcatFunctionIfNeeded(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('CONCAT', function (...$parts) {
                return implode('', $parts);
            });
        }
    }
}
