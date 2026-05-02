<?php

namespace Tests\Feature\Admin;

use App\Models\Doctors;
use App\Models\Notification;
use App\Models\PushNotificationCampaign;
use App\Models\Specialty;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
                })
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

    public function test_fcm_failure_is_counted_without_stopping_campaign(): void
    {
        $admin = $this->createUser('admin');
        $specialty = Specialty::create(['name' => 'Neurology']);
        $this->createDoctor($specialty->id, 'ok-token', 'ok@example.com');
        $this->createDoctor($specialty->id, 'failed-token', 'failed@example.com');

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('sendNotification')->with('ok-token', Mockery::any(), Mockery::any(), Mockery::any())->andReturn(['name' => 'ok']);
        $firebase->shouldReceive('sendNotification')->with('failed-token', Mockery::any(), Mockery::any(), Mockery::any())->andReturn(null);
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

    public function test_validation_errors_are_returned_for_missing_payload(): void
    {
        $admin = $this->createUser('admin');

        $response = $this->actingAs($admin)
            ->from('/admin/push-notifications')
            ->post('/admin/push-notifications/send', []);

        $response->assertRedirect('/admin/push-notifications');
        $response->assertSessionHasErrors(['specialty_id', 'title', 'body']);
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
