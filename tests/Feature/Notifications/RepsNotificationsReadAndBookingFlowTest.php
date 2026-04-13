<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorAvailability;
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

        $appointment = Appointment::query()
            ->where('doctors_id', $doctor->id)
            ->where('representative_id', $rep->id)
            ->firstOrFail();

        $expectedDedupeKey = sprintf(
            'appointment:%d:booked:to:doctor:%d',
            (int) $appointment->id,
            (int) $doctor->id
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'title' => 'New visit booked.',
            'body' => $expectedBody,
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => $expectedDedupeKey,
        ]);
    }

    public function test_booking_accepts_hour_minute_time_and_normalizes_slot(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'booking-format-rep@example.com', '01055555575');
        $doctor = $this->createDoctor('booking-format-doctor@example.com', '01066666676');

        Sanctum::actingAs($rep, ['representative']);

        $date = now('Africa/Cairo')->toDateString();
        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('code', 201);

        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $date)
                ->where('start_time', '10:00:00')
                ->where('end_time', '10:05:00')
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_booking_duplicate_pending_slot_returns_409_with_clear_message(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'dup-pending-rep@example.com', '01055555556');
        $otherRep = $this->createRepresentative($company, 'dup-pending-rep-other@example.com', '01055555559');
        $doctor = $this->createDoctor('dup-pending-doctor@example.com', '01066666667');

        $date = now()->toDateString();
        DB::table('appointments')->insert([
            'doctors_id' => $doctor->id,
            'representative_id' => $otherRep->id,
            'company_id' => $company->id,
            'date' => $date,
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => 'pending',
            'appointment_code' => '30000000-0000-4000-8000-000000000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:00:00',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'This time slot already has an active appointment (pending or confirmed).');
    }

    public function test_booking_duplicate_confirmed_slot_returns_409_with_clear_message(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'dup-confirmed-rep@example.com', '01055555557');
        $otherRep = $this->createRepresentative($company, 'dup-confirmed-rep-other@example.com', '01055555560');
        $doctor = $this->createDoctor('dup-confirmed-doctor@example.com', '01066666668');

        $date = now()->toDateString();
        DB::table('appointments')->insert([
            'doctors_id' => $doctor->id,
            'representative_id' => $otherRep->id,
            'company_id' => $company->id,
            'date' => $date,
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'confirmed',
            'appointment_code' => '30000000-0000-4000-8000-000000000002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '11:00:00',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'This time slot already has an active appointment (pending or confirmed).');
    }

    public function test_booking_same_slot_after_cancelled_allows_creation(): void
    {
        $company = $this->createCompany();
        $rep = $this->createRepresentative($company, 'rebook-rep@example.com', '01055555558');
        $doctor = $this->createDoctor('rebook-doctor@example.com', '01066666669');

        $date = now()->toDateString();
        DB::table('appointments')->insert([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => $date,
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'cancelled',
            'cancelled_by' => 'system',
            'appointment_code' => '30000000-0000-4000-8000-000000000003',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '12:00:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('code', 201);

        $this->assertEquals(
            2,
            Appointment::where('doctors_id', $doctor->id)
                ->count()
        );
        $this->assertEquals(
            1,
            Appointment::where('doctors_id', $doctor->id)
                ->where('status', 'cancelled')
                ->count()
        );
        $this->assertEquals(
            1,
            Appointment::where('doctors_id', $doctor->id)
                ->where('status', 'pending')
                ->count()
        );
    }

    public function test_booking_enforces_range_capacity_limit_with_single_allowed_visit(): void
    {
        $company = $this->createCompany();
        $firstRep = $this->createRepresentative($company, 'range-limit-one-rep-a@example.com', '01055555601');
        $secondRep = $this->createRepresentative($company, 'range-limit-one-rep-b@example.com', '01055555602');
        $doctor = $this->createDoctor('range-limit-one-doctor@example.com', '01066666701');

        $date = now('Africa/Cairo')->addDays(2)->toDateString();
        $weekday = strtolower(Carbon::parse($date, 'Africa/Cairo')->format('l'));

        DoctorAvailability::query()
            ->where('doctors_id', $doctor->id)
            ->where('date', $weekday)
            ->update(['max_reps_per_range' => 1]);

        Sanctum::actingAs($firstRep, ['representative']);
        $firstResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:05:00',
        ]);
        $firstResponse->assertStatus(201);

        Sanctum::actingAs($secondRep, ['representative']);
        $secondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:40:00',
        ]);
        $secondResponse->assertStatus(409);
        $secondResponse->assertJsonPath('message', 'This availability range has reached the maximum number of visits for this doctor.');
    }

    public function test_booking_enforces_range_capacity_limit_with_two_allowed_visits(): void
    {
        $company = $this->createCompany();
        $firstRep = $this->createRepresentative($company, 'range-limit-two-rep-a@example.com', '01055555603');
        $secondRep = $this->createRepresentative($company, 'range-limit-two-rep-b@example.com', '01055555604');
        $thirdRep = $this->createRepresentative($company, 'range-limit-two-rep-c@example.com', '01055555605');
        $doctor = $this->createDoctor('range-limit-two-doctor@example.com', '01066666702');

        $date = now('Africa/Cairo')->addDays(2)->toDateString();
        $weekday = strtolower(Carbon::parse($date, 'Africa/Cairo')->format('l'));

        DoctorAvailability::query()
            ->where('doctors_id', $doctor->id)
            ->where('date', $weekday)
            ->update(['max_reps_per_range' => 2]);

        Sanctum::actingAs($firstRep, ['representative']);
        $firstResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:05:00',
        ]);
        $firstResponse->assertStatus(201);

        Sanctum::actingAs($secondRep, ['representative']);
        $secondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:20:00',
        ]);
        $secondResponse->assertStatus(201);

        Sanctum::actingAs($thirdRep, ['representative']);
        $thirdResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '12:40:00',
        ]);
        $thirdResponse->assertStatus(409);
        $thirdResponse->assertJsonPath('message', 'This availability range has reached the maximum number of visits for this doctor.');
    }

    public function test_booking_range_capacity_is_scoped_per_non_overlapping_ranges(): void
    {
        $company = $this->createCompany();
        $firstRep = $this->createRepresentative($company, 'range-scope-rep-a@example.com', '01055555606');
        $secondRep = $this->createRepresentative($company, 'range-scope-rep-b@example.com', '01055555607');
        $thirdRep = $this->createRepresentative($company, 'range-scope-rep-c@example.com', '01055555608');
        $doctor = $this->createDoctor('range-scope-doctor@example.com', '01066666703');

        $date = now('Africa/Cairo')->addDays(2)->toDateString();
        $weekday = strtolower(Carbon::parse($date, 'Africa/Cairo')->format('l'));

        DoctorAvailability::query()
            ->where('doctors_id', $doctor->id)
            ->where('date', $weekday)
            ->delete();

        DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $weekday,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'ends_next_day' => false,
            'max_reps_per_range' => 1,
            'status' => 'available',
        ]);
        DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $weekday,
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'ends_next_day' => false,
            'max_reps_per_range' => 1,
            'status' => 'available',
        ]);

        Sanctum::actingAs($firstRep, ['representative']);
        $firstResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:05:00',
        ]);
        $firstResponse->assertStatus(201);

        Sanctum::actingAs($secondRep, ['representative']);
        $secondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '13:05:00',
        ]);
        $secondResponse->assertStatus(201);

        Sanctum::actingAs($thirdRep, ['representative']);
        $thirdResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => '10:20:00',
        ]);
        $thirdResponse->assertStatus(409);
    }

    public function test_booking_overnight_range_uses_single_shared_total_across_both_dates(): void
    {
        $company = $this->createCompany();
        $firstRep = $this->createRepresentative($company, 'range-overnight-rep-a@example.com', '01055555609');
        $secondRep = $this->createRepresentative($company, 'range-overnight-rep-b@example.com', '01055555610');
        $thirdRep = $this->createRepresentative($company, 'range-overnight-rep-c@example.com', '01055555611');
        $doctor = $this->createDoctor('range-overnight-doctor@example.com', '01066666704');

        $startDate = now('Africa/Cairo')->addDays(2)->toDateString();
        $endDate = Carbon::parse($startDate, 'Africa/Cairo')->addDay()->toDateString();
        $weekday = strtolower(Carbon::parse($startDate, 'Africa/Cairo')->format('l'));

        DoctorAvailability::query()
            ->where('doctors_id', $doctor->id)
            ->delete();

        DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $weekday,
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
            'ends_next_day' => true,
            'max_reps_per_range' => 2,
            'status' => 'available',
        ]);

        Sanctum::actingAs($firstRep, ['representative']);
        $firstResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $startDate,
            'start_time' => '22:30:00',
        ]);
        $firstResponse->assertStatus(201);

        Sanctum::actingAs($secondRep, ['representative']);
        $secondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $endDate,
            'start_time' => '00:30:00',
        ]);
        $secondResponse->assertStatus(201);

        Sanctum::actingAs($thirdRep, ['representative']);
        $thirdResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $endDate,
            'start_time' => '01:30:00',
        ]);
        $thirdResponse->assertStatus(409);
        $thirdResponse->assertJsonPath('message', 'This availability range has reached the maximum number of visits for this doctor.');
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

        $doctor = Doctors::create([
            'name' => 'Doctor',
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
            'fcm_token' => $fcmToken,
        ]);

        $this->createFullWeekAvailability($doctor);

        return $doctor;
    }

    private function createFullWeekAvailability(Doctors $doctor): void
    {
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $weekday) {
            DoctorAvailability::create([
                'doctors_id' => $doctor->id,
                'date' => $weekday,
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'ends_next_day' => false,
                'max_reps_per_range' => 2,
                'status' => 'available',
            ]);
        }
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'notifications',
            'doctor_blocks',
            'doctor_availabilities',
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

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedInteger('max_reps_per_range')->default(2);
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
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
