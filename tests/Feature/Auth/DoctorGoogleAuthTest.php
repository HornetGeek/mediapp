<?php

namespace Tests\Feature\Auth;

use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Representative;
use App\Models\Specialty;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
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

    public function test_google_auth_auto_registers_unknown_doctor_and_requires_profile_completion(): void
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
        $response->assertJsonPath('message', 'Doctor login successfully');
        $response->assertJsonPath('data.name', 'New Doctor');
        $response->assertJsonPath('data.email', 'new-doctor@example.com');
        $response->assertJsonPath('data.profile_required', true);
        $this->assertSame(['phone', 'address_1', 'specialty_id'], $response->json('data.missing_fields'));
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('doctors', [
            'name' => 'New Doctor',
            'email' => 'new-doctor@example.com',
            'google_id' => 'google-doctor-3',
        ]);
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

    public function test_google_register_allows_missing_phone_and_address(): void
    {
        $this->fakeGooglePayload([
            'sub' => 'google-doctor-7',
            'email' => 'optional-fields-doctor@example.com',
            'email_verified' => true,
            'name' => 'Optional Fields Doctor',
        ]);

        $response = $this->postJson('/api/doctor/google-register', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Optional Fields Doctor');
        $response->assertJsonPath('data.email', 'optional-fields-doctor@example.com');
        $response->assertJsonPath('data.profile_required', true);
        $this->assertSame(['phone', 'address_1', 'specialty_id'], $response->json('data.missing_fields'));
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('doctors', [
            'name' => 'Optional Fields Doctor',
            'email' => 'optional-fields-doctor@example.com',
            'google_id' => 'google-doctor-7',
            'phone' => null,
            'address_1' => null,
        ]);
    }

    public function test_google_register_can_create_availability_and_return_token_that_updates_profile(): void
    {
        $this->fakeGooglePayload([
            'sub' => 'google-doctor-8',
            'email' => 'availability-google-doctor@example.com',
            'email_verified' => true,
            'name' => 'Availability Google Doctor',
        ]);

        $authResponse = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'valid-google-token',
        ]);

        $authResponse->assertStatus(200);
        $authResponse->assertJsonPath('data.profile_required', true);
        $this->assertNotEmpty($authResponse->json('data.token'));

        $registerResponse = $this->postJson('/api/doctor/google-register', [
            'id_token' => 'valid-google-token',
            'address_1' => 'Onboarding Clinic',
            'phone' => '010 0000 0008',
            'specialty_id' => Specialty::create(['name' => 'Oncology', 'slug' => 'oncology'])->id,
            'available_times' => [
                [
                    'date' => 'monday',
                    'start_time' => '09:00 AM',
                    'end_time' => '10:00 AM',
                    'max_reps_per_range' => 3,
                    'visit_time_type' => 'between',
                ],
                [
                    'date' => 'tuesday',
                    'start_time' => '11:00',
                    'end_time' => '12:00',
                    'visit_time_type' => 'after',
                ],
            ],
        ]);

        $registerResponse->assertStatus(200);
        $registerResponse->assertJsonPath('data.profile_required', false);
        $this->assertNotEmpty($registerResponse->json('data.token'));
        $this->assertDatabaseHas('doctors', [
            'email' => 'availability-google-doctor@example.com',
            'address_1' => 'Onboarding Clinic',
            'phone' => '01000000008',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'date' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'max_reps_per_range' => 3,
            'visit_time_type' => 'between',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'date' => 'tuesday',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'visit_time_type' => 'after',
            'status' => 'available',
        ]);

        $editProfileResponse = $this
            ->withToken($registerResponse->json('data.token'))
            ->putJson('/api/doctor/edit-profile', [
                'address_1' => 'Updated Onboarding Clinic',
            ]);

        $editProfileResponse->assertStatus(200);
        $editProfileResponse->assertJsonPath('data.address_1', 'Updated Onboarding Clinic');
        $editProfileResponse->assertJsonCount(2, 'data.available_times');
    }

    public function test_incomplete_auto_registered_google_doctor_is_hidden_from_representative_discovery(): void
    {
        $specialty = Specialty::create(['name' => 'Dermatology', 'slug' => 'dermatology']);
        $completeDoctor = Doctors::create([
            'name' => 'Complete Doctor',
            'email' => 'complete-doctor@example.com',
            'phone' => '01000000010',
            'password' => 'secret123',
            'address_1' => 'Complete Clinic',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);
        DoctorAvailability::create([
            'doctors_id' => $completeDoctor->id,
            'date' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'available',
        ]);

        $this->fakeGooglePayload([
            'sub' => 'google-doctor-hidden',
            'email' => 'hidden-google-doctor@example.com',
            'email_verified' => true,
            'name' => 'Hidden Google Doctor',
        ]);

        $googleResponse = $this->postJson('/api/doctor/google-auth', [
            'id_token' => 'valid-google-token',
        ]);
        $googleResponse->assertStatus(200);
        $googleResponse->assertJsonPath('data.profile_required', true);

        $representative = Representative::create([
            'name' => 'Discovery Rep',
            'email' => 'discovery-rep@example.com',
            'phone' => '01111111111',
            'password' => 'secret123',
        ]);
        Sanctum::actingAs($representative, ['representative']);

        $listResponse = $this->getJson('/api/reps/doctors/all');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');
        $listResponse->assertJsonPath('data.0.email', 'complete-doctor@example.com');

        $searchResponse = $this->getJson('/api/reps/doctors/search?name=Hidden');
        $searchResponse->assertStatus(404);
        $searchResponse->assertJsonPath('message', 'No doctors found');

        $profileResponse = $this->getJson('/api/reps/docotr/' . Doctors::where('email', 'hidden-google-doctor@example.com')->value('id'));
        $profileResponse->assertStatus(404);
        $profileResponse->assertJsonPath('message', 'Doctor not found');
    }

    public function test_google_register_rejects_overlapping_availability_without_creating_doctor(): void
    {
        $this->fakeGooglePayload([
            'sub' => 'google-doctor-9',
            'email' => 'overlap-google-doctor@example.com',
            'email_verified' => true,
            'name' => 'Overlap Google Doctor',
        ]);

        $response = $this->postJson('/api/doctor/google-register', [
            'id_token' => 'valid-google-token',
            'available_times' => [
                [
                    'date' => 'monday',
                    'start_time' => '09:00 AM',
                    'end_time' => '11:00 AM',
                ],
                [
                    'date' => 'monday',
                    'start_time' => '10:00 AM',
                    'end_time' => '12:00 PM',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This time conflicts with an existing availability');
        $this->assertDatabaseMissing('doctors', [
            'email' => 'overlap-google-doctor@example.com',
        ]);
        $this->assertDatabaseCount('doctor_availabilities', 0);
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

    public function test_google_register_rejects_doctor_linked_to_different_google_account(): void
    {
        Doctors::create([
            'name' => 'Existing Doctor',
            'email' => 'existing-doctor@example.com',
            'google_id' => 'different-google-doctor',
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
        $response->assertJsonPath('message', 'Google account is already linked to another doctor account');
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
            'doctor_availabilities',
            'doctor_blocks',
            'doctor_representative_favorite',
            'appointments',
            'representatives',
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

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('doctor_representative_favorite', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->boolean('is_fav')->default(false);
            $table->timestamps();
        });

        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('blockable_id');
            $table->string('blockable_type');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('doctor_availability_id')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->default('pending');
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
