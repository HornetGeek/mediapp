<?php

namespace Tests\Feature\Auth;

use App\Http\Resources\AppointmentsResource;
use App\Imports\RepCompanyCatalogImport;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\RepCompanyCatalog;
use App\Models\Representative;
use App\Models\Specialty;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RepresentativeSelfRegistrationTest extends TestCase
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
        config(['reps.self_registered_daily_visits_limit' => 10]);
    }

    public function test_company_catalog_import_is_idempotent_and_deduplicates_names(): void
    {
        $rows = new Collection([
            ['Companies'],
            ['PHARCO'],
            ['AMOUN PHARM.CO.'],
            ['  pharco  '],
            [''],
        ]);

        $import = new RepCompanyCatalogImport();
        $import->collection($rows);
        (new RepCompanyCatalogImport())->collection($rows);

        $this->assertSame(2, RepCompanyCatalog::count());
        $this->assertDatabaseHas('rep_company_catalogs', [
            'name' => 'pharco',
            'normalized_name' => 'PHARCO',
        ]);

    }

    public function test_company_catalog_search_returns_active_ranked_matches(): void
    {
        RepCompanyCatalog::create([
            'name' => 'Inactive PHARCO',
            'normalized_name' => 'INACTIVE PHARCO',
            'rank' => 1,
            'status' => 'inactive',
        ]);
        $unranked = RepCompanyCatalog::create([
            'name' => 'PHARCO Unranked',
            'normalized_name' => 'PHARCO UNRANKED',
            'rank' => null,
            'status' => 'active',
        ]);
        $ranked = RepCompanyCatalog::create([
            'name' => 'PHARCO',
            'normalized_name' => 'PHARCO',
            'rank' => 5,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/reps/companies?search=PHARCO&page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.0.id', $ranked->id)
            ->assertJsonPath('data.0.name', 'PHARCO')
            ->assertJsonPath('data.1.id', $unranked->id)
            ->assertJsonCount(2, 'data');
    }

    public function test_listed_company_registration_creates_active_representative(): void
    {
        $catalog = RepCompanyCatalog::create([
            'name' => 'PHARCO',
            'normalized_name' => 'PHARCO',
            'rank' => 1,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/reps/register', [
            'name' => 'Self Rep',
            'email' => 'self-rep@example.com',
            'phone' => '+2001000000111',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
            'company_catalog_id' => $catalog->id,
            'requested_line_name' => 'Line A',
            'requested_area_names' => ['Nasr City', 'Maadi'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.registration_status', 'active')
            ->assertJsonPath('data.requested_line_name', 'Line A')
            ->assertJsonPath('data.requested_area_names.0', 'Nasr City')
            ->assertJsonPath('data.requested_area_names.1', 'Maadi')
            ->assertJsonPath('data.work_areas.0', 'Nasr City')
            ->assertJsonPath('data.work_areas.1', 'Maadi')
            ->assertJsonPath('data.work_lines.0', 'Line A')
            ->assertJsonPath('data.can_book', true)
            ->assertJsonPath('data.company.type', 'catalog')
            ->assertJsonPath('data.phone', '+201000000111')
            ->assertJsonPath('data.daily_visits_limit', 10);

        $this->assertDatabaseHas('representatives', [
            'email' => 'self-rep@example.com',
            'phone' => '+201000000111',
            'company_id' => null,
            'company_catalog_id' => $catalog->id,
            'requested_line_name' => 'Line A',
            'registration_status' => 'active',
            'daily_visits_limit' => 10,
        ]);

        $this->assertSame(
            ['Nasr City', 'Maadi'],
            Representative::where('email', 'self-rep@example.com')->first()->requested_area_names
        );
    }

    public function test_other_company_registration_creates_pending_representative_with_read_only_access(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = Doctors::create([
            'specialty_id' => $specialty->id,
            'name' => 'Read Only Doctor',
            'email' => 'read-only-doctor@example.com',
            'phone' => '01099999999',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/reps/register', [
            'name' => 'Other Rep',
            'email' => 'other-rep@example.com',
            'phone' => '01000000222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requested_company_name' => 'Missing Pharma',
            'requested_line_name' => 'Line B',
            'requested_area_names' => ['Giza', 'October'],
        ])->assertStatus(201)
            ->assertJsonPath('data.registration_status', 'pending')
            ->assertJsonPath('data.requested_line_name', 'Line B')
            ->assertJsonPath('data.work_areas.0', 'Giza')
            ->assertJsonPath('data.work_areas.1', 'October')
            ->assertJsonPath('data.work_lines.0', 'Line B')
            ->assertJsonPath('data.can_book', false)
            ->assertJsonPath('data.company.type', 'requested');

        $loginResponse = $this->postJson('/api/reps/login', [
            'email' => 'other-rep@example.com',
            'password' => 'password123',
            'fcm_token' => 'pending-fcm-token',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('data.registration_status', 'pending')
            ->assertJsonPath('data.can_book', false)
            ->assertJsonPath('data.representative.requested_line_name', 'Line B')
            ->assertJsonPath('data.representative.work_areas.0', 'Giza')
            ->assertJsonPath('data.representative.work_areas.1', 'October')
            ->assertJsonPath('data.representative.work_lines.0', 'Line B')
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.registration_status', 'pending')
            ->assertJsonPath('data.requested_line_name', 'Line B')
            ->assertJsonPath('data.work_areas.0', 'Giza')
            ->assertJsonPath('data.work_areas.1', 'October')
            ->assertJsonPath('data.work_lines.0', 'Line B');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/specialities')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/doctors/all')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/booked/appointments')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/visits/balance')
            ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/reps/booking', [
                'doctors_id' => $doctor->id,
                'date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
            ])
            ->assertStatus(403)
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/reps/cancel-appointment/1')
            ->assertStatus(403)
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/reps/add-favorite-doctor', [
                'doctor_id' => $doctor->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/reps/notifications/read')
            ->assertStatus(403)
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');
    }

    public function test_registration_rejects_duplicate_and_invalid_company_payloads(): void
    {
        Representative::create([
            'name' => 'Existing Rep',
            'email' => 'existing-rep@example.com',
            'phone' => '01000000333',
            'password' => Hash::make('password123'),
            'registration_status' => 'active',
            'status' => 'active',
        ]);

        $this->postJson('/api/reps/register', [
            'name' => 'Duplicate Rep',
            'email' => 'existing-rep@example.com',
            'phone' => '01000000444',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_catalog_id' => 999,
            'requested_line_name' => 'Line C',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This email is already registered as a representative.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.email.0', 'This email is already registered as a representative.');

        $this->postJson('/api/reps/register', [
            'name' => 'Invalid Company Rep',
            'email' => 'invalid-company-rep@example.com',
            'phone' => '01000000555',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_catalog_id' => 999,
            'requested_line_name' => 'Line C',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Selected company was not found.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.company_catalog_id.0', 'Selected company was not found.');
    }

    public function test_self_registration_requires_requested_line_name(): void
    {
        $catalog = RepCompanyCatalog::create([
            'name' => 'PHARCO',
            'normalized_name' => 'PHARCO',
            'rank' => 1,
            'status' => 'active',
        ]);

        $this->postJson('/api/reps/register', [
            'name' => 'Missing Line Rep',
            'email' => 'missing-line-rep@example.com',
            'phone' => '01000000888',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_catalog_id' => $catalog->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Please enter the line name.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.requested_line_name.0', 'Please enter the line name.');
    }

    public function test_rep_registration_returns_clear_errors_for_missing_company_and_password_mismatch(): void
    {
        $missingCompanyResponse = $this->postJson('/api/reps/register', [
            'name' => 'Missing Company Rep',
            'email' => 'missing-company-rep@example.com',
            'phone' => '01000000999',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requested_line_name' => 'Line D',
        ]);

        $missingCompanyResponse->assertStatus(422)
            ->assertJsonPath('message', 'Please select a listed company or enter your company name.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.company_catalog_id.0', 'Please select a listed company or enter your company name.')
            ->assertJsonPath('data.errors.requested_company_name.0', 'Please select a listed company or enter your company name.');

        $passwordMismatchResponse = $this->postJson('/api/reps/register', [
            'name' => 'Password Mismatch Rep',
            'email' => 'password-mismatch-rep@example.com',
            'phone' => '01000001010',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'requested_company_name' => 'Missing Pharma',
            'requested_line_name' => 'Line E',
        ]);

        $passwordMismatchResponse->assertStatus(422)
            ->assertJsonPath('message', 'Password confirmation does not match.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.password.0', 'Password confirmation does not match.');

        $shortPasswordResponse = $this->postJson('/api/reps/register', [
            'name' => 'Short Password Rep',
            'email' => 'short-password-rep@example.com',
            'phone' => '01000001011',
            'password' => 'abc12',
            'password_confirmation' => 'abc12',
            'requested_company_name' => 'Missing Pharma',
            'requested_line_name' => 'Line F',
        ]);

        $shortPasswordResponse->assertStatus(422)
            ->assertJsonPath('message', 'Password must be at least 6 characters.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.password.0', 'Password must be at least 6 characters.');
    }

    public function test_doctor_registration_normalizes_phone_and_accepts_six_character_password(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology']);

        $response = $this->postJson('/api/doctor/register', [
            'name' => 'Normalized Phone Doctor',
            'email' => 'normalized-phone-doctor@example.com',
            'phone' => '002001033333333',
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
            'address_1' => 'Nasr City',
            'specialty_id' => $specialty->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('doctors', [
            'email' => 'normalized-phone-doctor@example.com',
            'phone' => '00201033333333',
        ]);
    }

    public function test_doctor_registration_returns_clear_validation_errors(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology']);

        Doctors::create([
            'specialty_id' => $specialty->id,
            'name' => 'Existing Doctor',
            'email' => 'existing-doctor@example.com',
            'phone' => '01011111111',
            'password' => Hash::make('password123'),
            'address_1' => 'Nasr City',
            'status' => 'active',
        ]);

        $duplicateEmailResponse = $this->postJson('/api/doctor/register', [
            'name' => 'Duplicate Doctor',
            'email' => 'existing-doctor@example.com',
            'phone' => '01022222222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address_1' => 'Maadi',
            'specialty_id' => $specialty->id,
        ]);

        $duplicateEmailResponse->assertStatus(422)
            ->assertJsonPath('message', 'This email is already registered as a doctor.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.email.0', 'This email is already registered as a doctor.');

        $missingFieldsResponse = $this->postJson('/api/doctor/register', []);

        $missingFieldsResponse->assertStatus(422)
            ->assertJsonPath('message', 'Please enter the doctor name.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.name.0', 'Please enter the doctor name.')
            ->assertJsonPath('data.errors.email.0', 'Please enter the doctor email.')
            ->assertJsonPath('data.errors.phone.0', 'Please enter the doctor phone number.')
            ->assertJsonPath('data.errors.password.0', 'Please enter a password.')
            ->assertJsonPath('data.errors.address_1.0', 'Please enter the doctor address.');

        $invalidSpecialtyResponse = $this->postJson('/api/doctor/register', [
            'name' => 'Invalid Specialty Doctor',
            'email' => 'invalid-specialty-doctor@example.com',
            'phone' => '01033333333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address_1' => 'Giza',
            'specialty_id' => 999999,
        ]);

        $invalidSpecialtyResponse->assertStatus(422)
            ->assertJsonPath('message', 'Selected specialty was not found.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.specialty_id.0', 'Selected specialty was not found.');

        $shortPasswordResponse = $this->postJson('/api/doctor/register', [
            'name' => 'Short Password Doctor',
            'email' => 'short-password-doctor@example.com',
            'phone' => '01044444444',
            'password' => 'abc12',
            'password_confirmation' => 'abc12',
            'address_1' => 'Giza',
            'specialty_id' => $specialty->id,
        ]);

        $shortPasswordResponse->assertStatus(422)
            ->assertJsonPath('message', 'Password must be at least 6 characters.')
            ->assertJsonPath('data.error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('data.errors.password.0', 'Password must be at least 6 characters.');
    }

    public function test_existing_company_representative_login_still_blocks_inactive_company(): void
    {
        $company = Company::create([
            'name' => 'Inactive Company',
            'email' => 'inactive-company@example.com',
            'password' => Hash::make('password123'),
            'phone' => '01099999999',
            'visits_per_day' => 10,
            'num_of_reps' => 5,
            'status' => 'inactive',
        ]);

        Representative::create([
            'name' => 'Managed Rep',
            'email' => 'managed-rep@example.com',
            'phone' => '01000000666',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'registration_status' => 'active',
            'status' => 'active',
        ]);

        $this->postJson('/api/reps/login', [
            'email' => 'managed-rep@example.com',
            'password' => 'password123',
            'fcm_token' => 'managed-fcm',
        ])->assertStatus(401);
    }

    public function test_appointment_resource_serializes_catalog_company(): void
    {
        $specialty = Specialty::create(['name' => 'Cardiology']);
        $doctor = Doctors::create([
            'name' => 'Doctor',
            'email' => 'doctor@example.com',
            'phone' => '01012345678',
            'password' => Hash::make('password123'),
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);
        $catalog = RepCompanyCatalog::create([
            'name' => 'PHARCO',
            'normalized_name' => 'PHARCO',
            'rank' => 1,
            'status' => 'active',
        ]);
        $rep = Representative::create([
            'name' => 'Catalog Rep',
            'email' => 'catalog-resource-rep@example.com',
            'phone' => '01000000777',
            'password' => Hash::make('password123'),
            'company_catalog_id' => $catalog->id,
            'registration_status' => 'active',
            'daily_visits_limit' => 10,
            'status' => 'active',
        ]);

        $appointment = Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => null,
            'company_catalog_id' => $catalog->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => 'pending',
        ])->load(['doctor.specialty', 'representative', 'company', 'companyCatalog']);

        $payload = (new AppointmentsResource($appointment))->resolve();

        $this->assertSame('catalog', $payload['company']['type']);
        $this->assertSame($catalog->id, $payload['company']['id']);
        $this->assertSame('PHARCO', $payload['company']['name']);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'appointments',
            'doctor_representative_favorite',
            'doctor_blocks',
            'doctor_availabilities',
            'representatives',
            'companies',
            'rep_company_catalogs',
            'doctors',
            'specialties',
            'area_representative',
            'line_representative',
            'areas',
            'lines',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('rep_company_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->unsignedInteger('rank')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

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
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
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
            $table->unsignedInteger('max_reps_per_range')->default(1);
            $table->enum('status', ['available', 'canceled'])->default('available');
            $table->timestamps();
        });

        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('blockable_id');
            $table->string('blockable_type');
            $table->timestamps();
        });

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->string('requested_company_name')->nullable();
            $table->string('requested_line_name')->nullable();
            $table->json('requested_area_names')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('registration_status', ['active', 'pending', 'rejected'])->default('active');
            $table->unsignedInteger('daily_visits_limit')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('doctor_representative_favorite', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->boolean('is_fav')->default(false);
            $table->timestamps();
        });

        Schema::create('area_representative', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('representative_id');
            $table->timestamps();
        });

        Schema::create('line_representative', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('line_id');
            $table->unsignedBigInteger('representative_id');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->unsignedBigInteger('doctor_availability_id')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended', 'deleted'])->nullable();
            $table->uuid('appointment_code')->unique()->nullable();
            $table->string('cancelled_by')->nullable();
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
