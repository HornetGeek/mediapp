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
            'phone' => '01000000111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_catalog_id' => $catalog->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.registration_status', 'active')
            ->assertJsonPath('data.can_book', true)
            ->assertJsonPath('data.company.type', 'catalog')
            ->assertJsonPath('data.daily_visits_limit', 10);

        $this->assertDatabaseHas('representatives', [
            'email' => 'self-rep@example.com',
            'company_id' => null,
            'company_catalog_id' => $catalog->id,
            'registration_status' => 'active',
            'daily_visits_limit' => 10,
        ]);
    }

    public function test_other_company_registration_creates_pending_representative_that_can_only_login_and_profile(): void
    {
        $this->postJson('/api/reps/register', [
            'name' => 'Other Rep',
            'email' => 'other-rep@example.com',
            'phone' => '01000000222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'requested_company_name' => 'Missing Pharma',
        ])->assertStatus(201)
            ->assertJsonPath('data.registration_status', 'pending')
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
            ->assertJsonPath('data.error_code', 'REP_PENDING_APPROVAL');

        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.registration_status', 'pending');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reps/specialities')
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
        ])->assertStatus(422);

        $this->postJson('/api/reps/register', [
            'name' => 'Invalid Company Rep',
            'email' => 'invalid-company-rep@example.com',
            'phone' => '01000000555',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_catalog_id' => 999,
        ])->assertStatus(422);
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
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->string('requested_company_name')->nullable();
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
