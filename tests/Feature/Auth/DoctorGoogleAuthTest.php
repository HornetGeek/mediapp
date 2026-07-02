<?php

namespace Tests\Feature\Auth;

use App\Models\Doctors;
use App\Models\Specialty;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorGoogleAuthTest extends TestCase
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

    public function test_google_auth_logs_in_existing_doctor_by_google_id(): void
    {
        $doctor = Doctors::create([
            'name' => 'Google Doctor',
            'email' => 'doctor-google@example.com',
            'google_id' => 'google-doctor-1',
            'phone' => '01000000001',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-1',
            'email' => 'doctor-google@example.com',
            'email_verified' => true,
            'name' => 'Google Doctor',
            'picture' => 'https://example.com/avatar.png',
        ]);

        $response = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'valid-google-token',
            'fcm_token' => 'doctor-fcm-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Google Doctor');
        $response->assertJsonPath('data.email', 'doctor-google@example.com');
        $response->assertJsonPath('data.fcm_token', 'doctor-fcm-token');
        $this->assertNotEmpty($response->json('data.token'));

        $doctor->refresh();
        $this->assertSame('doctor-fcm-token', $doctor->fcm_token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_google_auth_links_existing_doctor_by_verified_email(): void
    {
        $doctor = Doctors::create([
            'name' => 'Email Doctor',
            'email' => 'email-doctor@example.com',
            'phone' => '01000000002',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-2',
            'email' => 'email-doctor@example.com',
            'email_verified' => true,
            'name' => 'Email Doctor',
        ]);

        $response = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.token'));

        $doctor->refresh();
        $this->assertSame('google-doctor-2', $doctor->google_id);
    }

    public function test_google_auth_for_unknown_doctor_requires_profile_completion(): void
    {
        $this->fakeGooglePayload([
            'sub' => 'google-doctor-3',
            'email' => 'new-doctor@example.com',
            'email_verified' => true,
            'name' => 'New Doctor',
            'picture' => 'https://example.com/new-avatar.png',
        ]);

        $response = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.profile_required', true);
        $response->assertJsonPath('data.google_user.email', 'new-doctor@example.com');
        $response->assertJsonPath('data.google_user.name', 'New Doctor');
        $this->assertDatabaseCount('doctors', 0);
    }

    public function test_google_register_creates_doctor_and_returns_token(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology', 'slug' => 'cardiology']);

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-4',
            'email' => 'register-doctor@example.com',
            'email_verified' => true,
            'name' => 'Register Doctor',
            'picture' => 'https://example.com/register-avatar.png',
        ]);

        $response = $this->postJson('/api/doctor/google-register', [
            'id_token' => 'valid-google-token',
            'phone' => '010 0000 0004',
            'address_1' => 'Clinic address',
            'specialty_id' => $specialty->id,
            'fcm_token' => 'new-fcm-token',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Register Doctor');
        $response->assertJsonPath('data.email', 'register-doctor@example.com');
        $response->assertJsonPath('data.fcm_token', 'new-fcm-token');
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('doctors', [
            'name' => 'Register Doctor',
            'email' => 'register-doctor@example.com',
            'google_id' => 'google-doctor-4',
            'phone' => '01000000004',
            'address_1' => 'Clinic address',
            'specialty_id' => $specialty->id,
            'fcm_token' => 'new-fcm-token',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_google_auth_rejects_invalid_or_unverified_google_token(): void
    {
        $this->fakeGooglePayload(null);

        $invalidResponse = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'invalid-google-token',
        ]);

        $invalidResponse->assertStatus(401);
        $invalidResponse->assertJsonPath('message', 'Invalid Google token');

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-5',
            'email' => 'unverified-doctor@example.com',
            'email_verified' => false,
        ]);

        $unverifiedResponse = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'unverified-google-token',
        ]);

        $unverifiedResponse->assertStatus(401);
        $unverifiedResponse->assertJsonPath('message', 'Invalid Google token');
    }

    public function test_google_register_rejects_existing_doctor(): void
    {
        Doctors::create([
            'name' => 'Existing Doctor',
            'email' => 'existing-doctor@example.com',
            'phone' => '01000000006',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-6',
            'email' => 'existing-doctor@example.com',
            'email_verified' => true,
            'name' => 'Existing Doctor',
        ]);

        $response = $this->postJson('/api/doctor/google-register', [
            'id_token' => 'valid-google-token',
            'phone' => '01000000006',
            'address_1' => 'Clinic address',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Doctor already exists, please login');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function fakeGooglePayload(?array $payload): void
    {
        $this->app->instance(GoogleIdTokenVerifier::class, new class($payload) extends GoogleIdTokenVerifier {
            public function __construct(private ?array $payload)
            {
            }

            public function verify(string $idToken): ?array
            {
                return $this->payload;
            }
        });
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
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
            $table->string('google_id')->nullable()->unique();
            $table->text('google_avatar')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('fcm_token')->nullable();
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
