<?php

namespace Tests\Feature\Notifications;

use App\Events\SendNotificationEvent;
use App\Listeners\SendFcmNotificationListener;
use App\Models\Doctors;
use App\Services\FirebaseNotificationService;
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

    public function test_listener_allows_same_dedupe_key_after_window_expires(): void
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
            ->twice()
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

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_listener_uses_fallback_dedupe_key_when_not_explicitly_provided(): void
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
            ->once()
            ->andReturn(['ok' => true]);

        $listener = new SendFcmNotificationListener($service);

        $listener->handle(new SendNotificationEvent($doctor, 'Fallback', 'Body', 'doctor'));
        $listener->handle(new SendNotificationEvent($doctor, 'Fallback', 'Body', 'doctor'));

        $this->assertDatabaseCount('notifications', 1);
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
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->timestamps();
            $table->index('dedupe_key', 'notifications_dedupe_key_index');
        });
    }
}
