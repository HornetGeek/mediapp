<?php

namespace Tests\Feature\Notifications;

use App\Models\Doctors;
use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DoctorProfileCompletionReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestingSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_sends_first_reminder_after_five_hours_for_incomplete_profile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('profile-first@example.com', now('Africa/Cairo')->subHours(5));

        Artisan::call('doctor:profile-completion-reminders');

        $notification = Notification::query()->firstOrFail();

        $this->assertSame('doctor_profile_completion_reminder:first:doctor:' . $doctor->id . ':period:2026-07-09', $notification->dedupe_key);
        $this->assertSame('doctor', $notification->target_type);
        $this->assertSame('doctor_profile_completion_reminder', $notification->data['type']);
        $this->assertSame('open_doctor_profile', $notification->data['action_type']);
        $this->assertSame('doctor_profile_edit', $notification->data['screen']);
        $this->assertSame('mediapp://doctor/profile/edit', $notification->data['deep_link']);
        $this->assertSame(['phone', 'address_1', 'specialty_id'], $notification->data['missing_fields']);
    }

    public function test_command_does_not_send_before_five_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $this->createDoctor('profile-too-new@example.com', now('Africa/Cairo')->subHours(4)->subMinutes(59));

        Artisan::call('doctor:profile-completion-reminders');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_sends_only_once_per_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $this->createDoctor('profile-once-daily@example.com', now('Africa/Cairo')->subHours(6));

        Artisan::call('doctor:profile-completion-reminders');
        Artisan::call('doctor:profile-completion-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_command_sends_daily_follow_up_next_day_if_still_incomplete(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('profile-daily@example.com', now('Africa/Cairo')->subHours(6));

        Artisan::call('doctor:profile-completion-reminders');

        Carbon::setTestNow(Carbon::parse('2026-07-10 08:00:00', 'Africa/Cairo'));

        Artisan::call('doctor:profile-completion-reminders');

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'dedupe_key' => 'doctor_profile_completion_reminder:daily:doctor:' . $doctor->id . ':period:2026-07-10',
        ]);
    }

    public function test_reminders_stop_after_required_profile_fields_are_filled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('profile-complete-later@example.com', now('Africa/Cairo')->subHours(6));

        Artisan::call('doctor:profile-completion-reminders');

        $doctor->update([
            'phone' => '01000000001',
            'address_1' => 'Clinic address',
            'specialty_id' => 10,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-07-10 08:00:00', 'Africa/Cairo'));

        Artisan::call('doctor:profile-completion-reminders');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notifications_api_returns_profile_completion_action_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $doctor = $this->createDoctor('profile-api@example.com', now('Africa/Cairo')->subHours(6));

        Artisan::call('doctor:profile-completion-reminders');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.data.type', 'doctor_profile_completion_reminder');
        $response->assertJsonPath('data.0.data.screen', 'doctor_profile_edit');
        $response->assertJsonPath('data.0.data.missing_fields.0', 'phone');
        $response->assertJsonPath('data.0.data.missing_fields.1', 'address_1');
        $response->assertJsonPath('data.0.data.missing_fields.2', 'specialty_id');
    }

    public function test_command_dedupes_duplicate_runs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'Africa/Cairo'));
        $this->mockFirebaseWithoutSends();

        $this->createDoctor('profile-dedupe@example.com', now('Africa/Cairo')->subHours(6));

        Artisan::call('doctor:profile-completion-reminders');
        Artisan::call('doctor:profile-completion-reminders');
        Artisan::call('doctor:profile-completion-reminders');

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
            'name' => 'Incomplete Profile Doctor',
            'email' => $email,
            'phone' => null,
            'password' => 'secret',
            'address_1' => null,
            'specialty_id' => null,
            'status' => 'active',
            'fcm_token' => null,
        ]);

        $doctor->created_at = $createdAt;
        $doctor->updated_at = $createdAt;
        $doctor->save();

        return $doctor->fresh();
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'notifications',
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
            $table->string('address_1')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
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
