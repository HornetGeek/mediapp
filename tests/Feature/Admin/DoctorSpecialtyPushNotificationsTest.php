<?php

namespace Tests\Feature\Admin;

use App\Models\Doctors;
use App\Models\Notification;
use App\Models\PushNotificationCampaign;
use App\Models\Specialty;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\VideoDurationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorSpecialtyPushNotificationsTest extends TestCase
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

    public function test_admin_can_send_push_notifications_to_doctors_by_specialty(): void
    {
        $admin = $this->createUser('admin');
        $cardiology = Specialty::create(['name' => 'Cardiology']);
        $dermatology = Specialty::create(['name' => 'Dermatology']);

        $targetWithToken = $this->createDoctor($cardiology->id, 'target-token', 'target-token@example.com');
        $targetWithoutToken = $this->createDoctor($cardiology->id, null, 'target-no-token@example.com');
        $otherSpecialty = $this->createDoctor($dermatology->id, 'other-token', 'other-token@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->with(
                'target-token',
                'Clinic Update',
                'Please review the latest clinic update.',
                Mockery::on(function (array $data) use ($cardiology) {
                    return $data['type'] === 'admin_push_notification'
                        && $data['target_type'] === 'doctor'
                        && (int) $data['specialty_id'] === (int) $cardiology->id
                        && !empty($data['campaign_id']);
                }),
                null
            )
            ->andReturn(['name' => 'projects/test/messages/1']);
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $response = $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $cardiology->id,
            'title' => 'Clinic Update',
            'body' => 'Please review the latest clinic update.',
        ]);

        $response->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertSame($admin->id, $campaign->sender_user_id);
        $this->assertSame($cardiology->id, $campaign->specialty_id);
        $this->assertSame('both', $campaign->delivery_type);
        $this->assertSame(2, $campaign->total_doctors);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $targetWithToken->id,
            'notifiable_type' => Doctors::class,
            'title' => 'Clinic Update',
            'target_type' => 'doctor',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $targetWithoutToken->id,
            'notifiable_type' => Doctors::class,
            'title' => 'Clinic Update',
            'target_type' => 'doctor',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $otherSpecialty->id,
            'notifiable_type' => Doctors::class,
            'title' => 'Clinic Update',
        ]);
    }

    public function test_admin_can_send_push_notifications_with_image_to_doctors_by_specialty(): void
    {
        config([
            'filesystems.disks.public.url' => 'https://mediapps.online/storage',
        ]);
        Storage::fake('public');

        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = $this->createDoctor($specialty->id, 'target-token', 'target-image@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->with(
                'target-token',
                'Image Notice',
                'Please review the image notice.',
                Mockery::on(function (array $data) use ($specialty) {
                    return $data['type'] === 'admin_push_notification'
                        && $data['target_type'] === 'doctor'
                        && (int) $data['specialty_id'] === (int) $specialty->id
                        && !empty($data['campaign_id']);
                }),
                Mockery::on(function (?string $imageUrl) {
                    return is_string($imageUrl)
                        && str_contains($imageUrl, '/storage/notification-campaigns/')
                        && str_ends_with($imageUrl, '.jpg');
                })
            )
            ->andReturn(['name' => 'projects/test/messages/with-image']);
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Image Notice',
            'body' => 'Please review the image notice.',
            'image' => UploadedFile::fake()->image('notice.jpg', 1200, 630),
        ])->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertNotNull($campaign->image_path);
        Storage::disk('public')->assertExists($campaign->image_path);

        $notification = Notification::where('notifiable_id', $doctor->id)->firstOrFail();
        $this->assertSame('Image Notice', $notification->title);
        $this->assertSame(Storage::disk('public')->url($campaign->image_path), $notification->image_url);

        Sanctum::actingAs($doctor, ['doctor']);

        $this->getJson('/api/doctor/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.media_type', 'image')
            ->assertJsonPath('data.0.display_type', 'list')
            ->assertJsonPath('data.0.image_url', $notification->image_url);
    }

    public function test_admin_can_send_non_skippable_modal_notification(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = $this->createDoctor($specialty->id, null, 'modal@example.com');

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Required Notice',
            'body' => 'Please acknowledge this notice.',
            'display_type' => 'modal',
        ])->assertRedirect(route('admin.push-notifications.index'));

        $notification = Notification::where('notifiable_id', $doctor->id)->firstOrFail();
        $this->assertSame('modal', $notification->display_type);
        $this->assertFalse((bool) $notification->is_skippable);

        Sanctum::actingAs($doctor, ['doctor']);

        $this->getJson('/api/doctor/notifications/modals')
            ->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Required Notice')
            ->assertJsonPath('data.0.display_type', 'modal')
            ->assertJsonPath('data.0.is_skippable', false)
            ->assertJsonPath('data.0.media_type', 'none');

        $this->putJson('/api/doctor/notifications/' . $notification->id . '/acknowledge')
            ->assertStatus(200)
            ->assertJsonPath('data.acknowledged_at', fn ($value) => is_string($value) && $value !== '');

        $this->getJson('/api/doctor/notifications/modals')
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_admin_can_send_modal_notification_with_video(): void
    {
        Storage::fake('public');

        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = $this->createDoctor($specialty->id, 'video-token', 'video@example.com');

        $duration = Mockery::mock(VideoDurationService::class);
        $duration->shouldReceive('validateMaxDuration')->once()->andReturn(null);
        $this->app->instance(VideoDurationService::class, $duration);

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->with(
                'video-token',
                'Video Notice',
                'Please review this video.',
                Mockery::on(function (array $data) {
                    return $data['type'] === 'admin_push_notification'
                        && $data['display_type'] === 'modal'
                        && $data['is_skippable'] === '1'
                        && $data['media_type'] === 'video';
                }),
                null
            )
            ->andReturn(['name' => 'projects/test/messages/video']);
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Video Notice',
            'body' => 'Please review this video.',
            'display_type' => 'modal',
            'is_skippable' => '1',
            'video' => UploadedFile::fake()->create('notice.mp4', 1024, 'video/mp4'),
        ])->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertSame('video', $campaign->media_type);
        $this->assertNotNull($campaign->video_path);
        Storage::disk('public')->assertExists($campaign->video_path);

        $notification = Notification::where('notifiable_id', $doctor->id)->firstOrFail();
        $this->assertSame('video', $notification->media_type);
        $this->assertNull($notification->image_url);
        $this->assertNotNull($notification->video_url);

        Sanctum::actingAs($doctor, ['doctor']);

        $this->getJson('/api/doctor/notifications/modals')
            ->assertStatus(200)
            ->assertJsonPath('data.0.media_type', 'video')
            ->assertJsonPath('data.0.video_url', $notification->video_url);
    }

    public function test_fcm_failure_is_counted_without_stopping_campaign(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Neurology']);
        $this->createDoctor($specialty->id, 'ok-token', 'ok@example.com');
        $this->createDoctor($specialty->id, 'failed-token', 'failed@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')->with('ok-token', Mockery::any(), Mockery::any(), Mockery::any(), null)->andReturn(['name' => 'ok']);
        $firebase->shouldReceive('sendNotification')->with('failed-token', Mockery::any(), Mockery::any(), Mockery::any(), null)->andReturn(null);
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Schedule',
            'body' => 'Schedule changed.',
        ])->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertSame(2, $campaign->total_doctors);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame(2, Notification::count());
    }

    public function test_push_only_delivery_sends_fcm_without_creating_in_app_notifications(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $this->createDoctor($specialty->id, 'push-token', 'push-only@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->with(
                'push-token',
                'Push Only',
                'This should not appear in app.',
                Mockery::on(fn (array $data) => ($data['delivery_type'] ?? null) === 'push_only'),
                null
            )
            ->andReturn(['name' => 'push-only']);
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Push Only',
            'body' => 'This should not appear in app.',
            'delivery_type' => 'push_only',
            'display_type' => 'modal',
        ])->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertSame('push_only', $campaign->delivery_type);
        $this->assertSame('list', $campaign->display_type);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, Notification::count());
    }

    public function test_in_app_only_delivery_creates_notifications_without_sending_fcm(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = $this->createDoctor($specialty->id, 'in-app-token', 'in-app-only@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')->never();
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'In App Only',
            'body' => 'This should only appear in app.',
            'delivery_type' => 'in_app_only',
            'display_type' => 'modal',
        ])->assertRedirect(route('admin.push-notifications.index'));

        $campaign = PushNotificationCampaign::firstOrFail();
        $this->assertSame('in_app_only', $campaign->delivery_type);
        $this->assertSame(0, $campaign->sent_count);

        $notification = Notification::where('notifiable_id', $doctor->id)->firstOrFail();
        $this->assertSame('modal', $notification->display_type);
        $this->assertSame('In App Only', $notification->title);
    }

    public function test_validation_errors_are_returned_for_missing_payload(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)
            ->from('/admin/push-notifications')
            ->post('/admin/push-notifications/send', []);

        $response->assertRedirect('/admin/push-notifications');
        $response->assertSessionHasErrors(['specialty_id', 'title', 'body']);
    }

    public function test_invalid_push_notification_image_returns_validation_error(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);

        $response = $this->actingAs($admin)
            ->from('/admin/push-notifications')
            ->post('/admin/push-notifications/send', [
                'specialty_id' => $specialty->id,
                'title' => 'Invalid Image',
                'body' => 'Invalid image payload.',
                'image' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf'),
            ]);

        $response->assertRedirect('/admin/push-notifications');
        $response->assertSessionHasErrors('image');
    }

    public function test_video_longer_than_twenty_seconds_returns_validation_error(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);

        $duration = Mockery::mock(VideoDurationService::class);
        $duration->shouldReceive('validateMaxDuration')
            ->once()
            ->andReturn('Video duration must not exceed 20 seconds.');
        $this->app->instance(VideoDurationService::class, $duration);

        $response = $this->actingAs($admin)
            ->from('/admin/push-notifications')
            ->post('/admin/push-notifications/send', [
                'specialty_id' => $specialty->id,
                'title' => 'Long Video',
                'body' => 'Invalid video payload.',
                'video' => UploadedFile::fake()->create('long.mp4', 1024, 'video/mp4'),
            ]);

        $response->assertRedirect('/admin/push-notifications');
        $response->assertSessionHasErrors('video');
    }

    public function test_push_only_delivery_rejects_video_upload(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Cardiology']);

        $duration = Mockery::mock(VideoDurationService::class);
        $duration->shouldReceive('validateMaxDuration')->never();
        $this->app->instance(VideoDurationService::class, $duration);

        $response = $this->actingAs($admin)
            ->from('/admin/push-notifications')
            ->post('/admin/push-notifications/send', [
                'specialty_id' => $specialty->id,
                'title' => 'Push Video',
                'body' => 'Invalid delivery for video.',
                'delivery_type' => 'push_only',
                'video' => UploadedFile::fake()->create('notice.mp4', 1024, 'video/mp4'),
            ]);

        $response->assertRedirect('/admin/push-notifications');
        $response->assertSessionHasErrors('video');
    }

    public function test_admin_routes_require_admin_session(): void
    {
        $this->get('/admin/push-notifications')->assertRedirect('/');

        $admin = $this->createUser('admin');
        $this->actingAs($admin)
            ->get('/admin/push-notifications')
            ->assertStatus(200);
    }

    public function test_existing_doctor_notifications_endpoint_returns_admin_sent_notifications(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Pediatrics']);
        $doctor = $this->createDoctor($specialty->id, null, 'pediatrics@example.com');

        $this->actingAs($admin)->post('/admin/push-notifications/send', [
            'specialty_id' => $specialty->id,
            'title' => 'Admin Notice',
            'body' => 'Please check the dashboard notice.',
        ])->assertRedirect(route('admin.push-notifications.index'));

        Sanctum::actingAs($doctor, ['doctor']);

        $this->getJson('/api/doctor/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Admin Notice')
            ->assertJsonPath('data.0.body', 'Please check the dashboard notice.')
            ->assertJsonPath('data.0.display_type', 'list')
            ->assertJsonPath('data.0.is_skippable', true)
            ->assertJsonPath('data.0.media_type', 'none')
            ->assertJsonPath('data.0.image_url', null)
            ->assertJsonPath('data.0.video_url', null)
            ->assertJsonPath('data.0.target_type', 'doctor');
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function createDoctor(int $specialtyId, ?string $fcmToken, string $email): Doctors
    {
        return Doctors::create([
            'name' => 'Doctor ' . $email,
            'email' => $email,
            'phone' => '010' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'specialty_id' => $specialtyId,
            'status' => 'active',
            'fcm_token' => $fcmToken,
        ]);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'notifications',
            'push_notification_campaigns',
            'personal_access_tokens',
            'doctors',
            'specialties',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'super_admin'])->default('super_admin');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specialty_id')->nullable();
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
            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->enum('media_type', ['none', 'image', 'video'])->default('none');
            $table->enum('display_type', ['list', 'modal'])->default('list');
            $table->boolean('is_skippable')->default(true);
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->enum('target_type', ['doctor', 'reps', 'company'])->nullable();
            $table->string('dedupe_key')->nullable();
            $table->char('dedupe_fingerprint', 64)->nullable()->unique();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->timestamps();
        });

        Schema::create('push_notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->unsignedBigInteger('specialty_id');
            $table->string('title');
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->enum('display_type', ['list', 'modal'])->default('list');
            $table->boolean('is_skippable')->default(true);
            $table->enum('media_type', ['none', 'image', 'video'])->default('none');
            $table->enum('delivery_type', ['both', 'push_only', 'in_app_only'])->default('both');
            $table->unsignedInteger('total_doctors')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
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
