<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DoctorAvailabilityReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_sends_first_setup_reminder_to_active_doctor_without_availability(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-first@example.com', now('Africa/Cairo')->subHours(25));

        Artisan::call('doctor:availability-retention-reminders');

        $notification = Notification::query()->firstOrFail();

        $this->assertSame('doctor_availability_reminder:first_setup:doctor:' . $doctor->id . ':period:2026-06-01', $notification->dedupe_key);
        $this->assertSame('doctor', $notification->target_type);
        $this->assertSame('first_setup', $notification->data['stage']);
        $this->assertSame('open_availability_setup', $notification->data['action_type']);
        $this->assertSame('mediapp://doctor/availability/setup', $notification->data['deep_link']);
    }

    public function test_notifications_api_returns_reminder_action_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-api@example.com', now('Africa/Cairo')->subHours(25));

        Artisan::call('doctor:availability-retention-reminders');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.data.type', 'doctor_availability_reminder');
        $response->assertJsonPath('data.0.data.screen', 'doctor_availability_setup');
        $response->assertJsonPath('data.0.data.stage', 'first_setup');
    }

    public function test_command_skips_doctor_with_open_bookable_capacity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-open-capacity@example.com', now('Africa/Cairo')->subDays(2));
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 1);

        Artisan::call('doctor:availability-retention-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_sends_capacity_reminder_when_all_upcoming_capacity_is_full(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-full-capacity@example.com', now('Africa/Cairo')->subDays(10));
        $availability = $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 1);

        foreach (['2026-06-01', '2026-06-08', '2026-06-15'] as $date) {
            $this->createAppointment($doctor, $availability, $date);
        }

        Artisan::call('doctor:availability-retention-reminders');

        $notification = Notification::query()->firstOrFail();

        $this->assertSame('capacity_empty', $notification->data['stage']);
        $this->assertSame(
            'doctor_availability_reminder:capacity_empty:doctor:' . $doctor->id . ':week:' . now('Africa/Cairo')->format('o-\WW'),
            $notification->dedupe_key
        );
    }

    public function test_command_sends_monthly_planning_reminder_on_25th_when_next_month_capacity_is_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-monthly@example.com', now('Africa/Cairo')->subDays(10));
        $availability = $this->createAvailability($doctor, '2026-06-26', '09:00:00', '10:00:00', 1);
        $this->createAppointment($doctor, $availability, '2026-06-26');

        Artisan::call('doctor:availability-retention-reminders');

        $notification = Notification::query()->firstOrFail();

        $this->assertSame('monthly_planning', $notification->data['stage']);
        $this->assertSame(
            'doctor_availability_reminder:monthly_planning:doctor:' . $doctor->id . ':month:2026-06',
            $notification->dedupe_key
        );
    }

    public function test_command_dedupes_first_setup_stage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $this->createDoctor('doctor-reminder-dedupe@example.com', now('Africa/Cairo')->subHours(25));

        Artisan::call('doctor:availability-retention-reminders');
        Artisan::call('doctor:availability-retention-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_no_availability_follow_up_stages_progress_after_previous_reminders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-05 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-followup@example.com', now('Africa/Cairo')->subDays(10));
        $this->createReminderNotification($doctor, 'first_setup', '2026-06-01', Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));

        Artisan::call('doctor:availability-retention-reminders');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'dedupe_key' => 'doctor_availability_reminder:follow_up_3d:doctor:' . $doctor->id . ':period:2026-06-05',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-13 08:00:00', 'Africa/Cairo'));

        Artisan::call('doctor:availability-retention-reminders');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'dedupe_key' => 'doctor_availability_reminder:follow_up_7d:doctor:' . $doctor->id . ':period:2026-06-13',
        ]);
    }

    public function test_reminders_stop_after_doctor_adds_useful_availability(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('doctor-reminder-stop@example.com', now('Africa/Cairo')->subHours(25));

        Artisan::call('doctor:availability-retention-reminders');
        $this->assertDatabaseCount('notifications', 1);

        $this->createAvailability($doctor, 'tuesday', '09:00:00', '10:00:00', 1);
        Carbon::setTestNow(Carbon::parse('2026-06-02 08:00:00', 'Africa/Cairo'));

        Artisan::call('doctor:availability-retention-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }

    private function mockFirebaseWithoutSends(): void
    {
        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')->never();

        $this->app->instance(FirebaseNotificationService::class, $service);
    }

    private function createDoctor(string $email, Carbon $createdAt): Doctors
    {
        $doctor = Doctors::create([
            'name' => 'Reminder Doctor',
            'email' => $email,
            'phone' => null,
            'password' => 'secret',
            'status' => 'active',
            'fcm_token' => null,
        ]);

        $doctor->created_at = $createdAt;
        $doctor->updated_at = $createdAt;
        $doctor->save();

        return $doctor->fresh();
    }

    private function createAvailability(
        Doctors $doctor,
        string $weekday,
        string $startTime,
        string $endTime,
        ?int $maxRepsPerRange
    ): DoctorAvailability {
        return DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $weekday,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'ends_next_day' => false,
            'max_reps_per_range' => $maxRepsPerRange,
            'visit_time_type' => 'between',
            'status' => 'available',
        ]);
    }

    private function createAppointment(Doctors $doctor, DoctorAvailability $availability, string $date): Appointment
    {
        return Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => 1,
            'company_id' => 1,
            'company_catalog_id' => null,
            'doctor_availability_id' => $availability->id,
            'date' => $date,
            'start_time' => (string) $availability->start_time,
            'end_time' => (string) $availability->end_time,
            'status' => 'pending',
        ]);
    }

    private function createReminderNotification(Doctors $doctor, string $stage, string $period, Carbon $createdAt): Notification
    {
        $dedupeKey = 'doctor_availability_reminder:' . $stage . ':doctor:' . $doctor->id . ':period:' . $period;
        $notification = Notification::create([
            'title' => 'أضف مواعيد الزيارات',
            'body' => 'حدد مواعيدك المتاحة ليتمكن المندوبون من حجز زياراتهم.',
            'data' => [
                'type' => 'doctor_availability_reminder',
                'stage' => $stage,
            ],
            'target_type' => 'doctor',
            'dedupe_key' => $dedupeKey,
            'dedupe_fingerprint' => hash('sha256', Doctors::class . '|' . $doctor->id . '|' . $dedupeKey),
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
        ]);

        $notification->created_at = $createdAt;
        $notification->updated_at = $createdAt;
        $notification->save();

        return $notification;
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'notifications',
            'appointments',
            'doctor_availabilities',
            'doctors',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedInteger('max_reps_per_range')->nullable()->default(null);
            $table->enum('visit_time_type', ['before', 'after', 'between'])->nullable()->default('between');
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->unsignedBigInteger('doctor_availability_id')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended', 'deleted'])->default('pending');
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->enum('media_type', ['none', 'image', 'video'])->default('none');
            $table->enum('display_type', ['list', 'modal'])->default('list');
            $table->boolean('is_skippable')->default(true);
            $table->timestamp('acknowledged_at')->nullable();
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
