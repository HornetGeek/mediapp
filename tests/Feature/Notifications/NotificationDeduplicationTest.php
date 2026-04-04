<?php

namespace Tests\Feature\Notifications;

use App\Events\SendNotificationEvent;
use App\Listeners\SendFcmNotificationListener;
use App\Models\Doctors;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class NotificationDeduplicationTest extends TestCase
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

    public function test_listener_skips_duplicate_notification_within_dedupe_window(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor One',
            'email' => 'doctor-one@example.com',
            'phone' => '01010000001',
            'password' => 'secret',
            'fcm_token' => 'token-1',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->once()
            ->andReturn(['ok' => true]);

        $listener = new SendFcmNotificationListener($service);
        $event = new SendNotificationEvent(
            $doctor,
            'Reminder',
            'Hello',
            'doctor',
            [],
            'fixed-dedupe-key',
            10
        );

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'dedupe_key' => 'fixed-dedupe-key',
            'title' => 'Reminder',
        ]);
    }

    public function test_listener_still_skips_same_dedupe_key_after_previous_window_expires(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor Two',
            'email' => 'doctor-two@example.com',
            'phone' => '01010000002',
            'password' => 'secret',
            'fcm_token' => 'token-2',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->once()
            ->andReturn(['ok' => true]);

        $listener = new SendFcmNotificationListener($service);
        $event = new SendNotificationEvent(
            $doctor,
            'Reminder',
            'Hello again',
            'doctor',
            [],
            'same-key',
            10
        );

        $listener->handle($event);

        Carbon::setTestNow('2026-06-01 10:11:00');
        $listener->handle($event);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_listener_skips_notification_when_dedupe_key_is_missing(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor Three',
            'email' => 'doctor-three@example.com',
            'phone' => '01010000003',
            'password' => 'secret',
            'fcm_token' => 'token-3',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->never();

        $listener = new SendFcmNotificationListener($service);

        $listener->handle(new SendNotificationEvent($doctor, 'Missing', 'Body', 'doctor'));

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_listener_skips_fcm_when_insert_or_ignore_detects_existing_fingerprint(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor Four',
            'email' => 'doctor-four@example.com',
            'phone' => '01010000004',
            'password' => 'secret',
            'fcm_token' => 'token-4',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')->never();

        $dedupeKey = 'pre-seeded-key';
        $fingerprint = hash('sha256', implode('|', [
            Doctors::class,
            (string) $doctor->id,
            $dedupeKey,
        ]));

        Notification::query()->insert([
            'title' => 'Reminder',
            'body' => 'Pre-seeded',
            'is_read' => false,
            'target_type' => 'doctor',
            'dedupe_key' => $dedupeKey,
            'dedupe_fingerprint' => $fingerprint,
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $listener = new SendFcmNotificationListener($service);
        $listener->handle(new SendNotificationEvent(
            $doctor,
            'Reminder',
            'Pre-seeded',
            'doctor',
            [],
            $dedupeKey,
            10
        ));

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_monthly_reminder_command_is_idempotent_per_doctor_per_month(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor Five',
            'email' => 'doctor-five@example.com',
            'phone' => '01010000005',
            'password' => 'secret',
            'fcm_token' => 'token-5',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->once()
            ->andReturn(['ok' => true]);

        $this->app->instance(FirebaseNotificationService::class, $service);

        Artisan::call('doctor:monthly-reminder');
        Artisan::call('doctor:monthly-reminder');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'dedupe_key' => 'monthly_availability_reminder:doctor:' . $doctor->id . ':month:2026-06',
        ]);
    }

    public function test_listener_does_not_retry_same_event_after_send_failure(): void
    {
        Carbon::setTestNow('2026-06-01 09:00:00');

        $doctor = Doctors::create([
            'name' => 'Doctor Six',
            'email' => 'doctor-six@example.com',
            'phone' => '01010000006',
            'password' => 'secret',
            'fcm_token' => 'token-6',
        ]);

        $service = Mockery::mock(FirebaseNotificationService::class);
        $service->shouldReceive('sendNotification')
            ->once()
            ->andThrow(new \RuntimeException('FCM down'));

        $listener = new SendFcmNotificationListener($service);
        $event = new SendNotificationEvent(
            $doctor,
            'Retry check',
            'Should not retry automatically',
            'doctor',
            [],
            'failure-key'
        );

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
            'notifiable_type' => Doctors::class,
            'dedupe_key' => 'failure-key',
        ]);
    }

    private function createTestingSchema(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('doctors');

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
