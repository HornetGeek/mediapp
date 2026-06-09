<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyAreasTest extends TestCase
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

    public function test_company_can_edit_own_area(): void
    {
        $company = $this->createCompany('company-areas@example.com');
        $area = $this->createArea('Old Area Name', $company->id);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-area/' . $area->id, [
            'name' => 'New Area Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Area updated successfully')
            ->assertJsonPath('data.id', $area->id)
            ->assertJsonPath('data.name', 'New Area Name')
            ->assertJsonPath('data.company_name', 'Company Areas');

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'name' => 'New Area Name',
            'company_id' => $company->id,
        ]);
    }

    public function test_company_cannot_edit_another_company_area(): void
    {
        $company = $this->createCompany('company-area-owner@example.com');
        $otherCompany = $this->createCompany('company-area-other@example.com');
        $area = $this->createArea('Other Company Area', $otherCompany->id);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-area/' . $area->id, [
            'name' => 'Updated By Wrong Company',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', 'Area not found');

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'name' => 'Other Company Area',
            'company_id' => $otherCompany->id,
        ]);
    }

    public function test_edit_area_requires_name(): void
    {
        $company = $this->createCompany('company-area-validation@example.com');
        $area = $this->createArea('Validation Area', $company->id);

        Sanctum::actingAs($company, ['company']);

        $response = $this->putJson('/api/company/edit-area/' . $area->id, []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422)
            ->assertJsonPath('message', 'Validation Error');
    }

    public function test_edit_area_requires_authentication(): void
    {
        $response = $this->putJson('/api/company/edit-area/1', [
            'name' => 'Unauthenticated Update',
        ]);

        $response->assertStatus(401);
    }

    private function createCompany(string $email): Company
    {
        return Company::create([
            'name' => 'Company Areas',
            'package_id' => null,
            'phone' => '01000001000',
            'email' => $email,
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 2,
            'status' => 'active',
        ]);
    }

    private function createArea(string $name, int $companyId): Area
    {
        $area = new Area();
        $area->name = $name;
        $area->company_id = $companyId;
        $area->save();

        return $area;
    }

    private function createTestingSchema(): void
    {
        Schema::dropIfExists('areas');
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

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });
    }
}
