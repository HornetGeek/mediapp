<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Line;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyLinesTest extends TestCase
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

    public function test_company_can_edit_own_line(): void
    {
        $company = $this->createCompany('company-lines@example.com');
        $line = Line::create([
            'name' => 'Old Line Name',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-line/' . $line->id, [
            'name' => 'New Line Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Line updated successfully')
            ->assertJsonPath('data.id', $line->id)
            ->assertJsonPath('data.name', 'New Line Name')
            ->assertJsonPath('data.company_name', 'Company Lines');

        $this->assertDatabaseHas('lines', [
            'id' => $line->id,
            'name' => 'New Line Name',
            'company_id' => $company->id,
        ]);
    }

    public function test_company_cannot_edit_another_company_line(): void
    {
        $company = $this->createCompany('company-owner@example.com');
        $otherCompany = $this->createCompany('company-other@example.com');
        $line = Line::create([
            'name' => 'Other Company Line',
            'company_id' => $otherCompany->id,
        ]);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-line/' . $line->id, [
            'name' => 'Updated By Wrong Company',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', 'Line not found');

        $this->assertDatabaseHas('lines', [
            'id' => $line->id,
            'name' => 'Other Company Line',
            'company_id' => $otherCompany->id,
        ]);
    }

    public function test_edit_line_requires_name(): void
    {
        $company = $this->createCompany('company-validation@example.com');
        $line = Line::create([
            'name' => 'Validation Line',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-line/' . $line->id, []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422)
            ->assertJsonPath('message', 'Validation Error');
    }

    public function test_edit_line_requires_authentication(): void
    {
        $response = $this->putJson('/api/company/edit-line/1', [
            'name' => 'Unauthenticated Update',
        ]);

        $response->assertStatus(401);
    }

    private function createCompany(string $email): Company
    {
        return Company::create([
            'name' => 'Company Lines',
            'package_id' => null,
            'phone' => '01000001000',
            'email' => $email,
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 2,
            'status' => 'active',
        ]);
    }

    private function createTestingSchema(): void
    {
        Schema::dropIfExists('lines');
        Schema::dropIfExists('companies');

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

        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });
    }
}
