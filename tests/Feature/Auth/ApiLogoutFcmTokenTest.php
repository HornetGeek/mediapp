<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\Doctors;
use App\Models\Representative;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiLogoutFcmTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    public function test_company_logout_clears_fcm_token_and_revokes_current_token(): void
    {
        $company = Company::create([
            'name' => 'ACME',
            'package_id' => 1,
            'phone' => '01000001000',
            'email' => 'company-logout@example.com',
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 2,
            'status' => 'active',
            'fcm_token' => 'company-fcm-token',
        ]);

        $token = $company->createToken('company-token', ['company']);
        $plainToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/company/logout');

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Company Logged Out Successfully');

        $company->refresh();
        $this->assertNull($company->fcm_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_doctor_logout_clears_fcm_token_and_revokes_current_token(): void
    {
        $doctor = Doctors::create([
            'name' => 'Doctor Logout',
            'email' => 'doctor-logout@example.com',
            'phone' => '01000002000',
            'password' => 'secret123',
            'status' => 'active',
            'fcm_token' => 'doctor-fcm-token',
        ]);

        $token = $doctor->createToken('doctor-token', ['doctor']);
        $plainToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/doctor/logout');

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Doctor logged out successfully');

        $doctor->refresh();
        $this->assertNull($doctor->fcm_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_representative_logout_clears_fcm_token_and_revokes_current_token(): void
    {
        $representative = Representative::create([
            'name' => 'Rep Logout',
            'email' => 'rep-logout@example.com',
            'phone' => '01000003000',
            'password' => 'secret123',
            'company_id' => 1,
            'status' => 'active',
            'fcm_token' => 'rep-fcm-token',
        ]);

        $token = $representative->createToken('rep-token', ['representative']);
        $plainToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/reps/logout');

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Representative logged out successfully');

        $representative->refresh();
        $this->assertNull($representative->fcm_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_unauthenticated_logout_requests_are_rejected(): void
    {
        $this->postJson('/api/company/logout')->assertStatus(401);
        $this->postJson('/api/doctor/logout')->assertStatus(401);
        $this->postJson('/api/reps/logout')->assertStatus(401);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'companies',
            'doctors',
            'representatives',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
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
