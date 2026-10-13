<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Company;
use App\Models\Line;
use App\Models\RepCompanyCatalog;
use App\Models\Representative;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperadminRepresentativesListTest extends TestCase
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

    public function test_superadmin_sees_all_representatives_and_company_sources(): void
    {
        $company = Company::create(['name' => 'Managed Pharma', 'email' => 'managed@example.com', 'password' => 'secret']);
        $catalog = RepCompanyCatalog::create(['name' => 'Catalog Pharma', 'normalized_name' => 'CATALOG PHARMA']);

        $managed = $this->createRepresentative('Managed Rep', 'managed-rep@example.com', '01011111111', [
            'company_id' => $company->id,
        ]);
        $this->createRepresentative('Catalog Rep', 'catalog-rep@example.com', '01022222222', [
            'company_catalog_id' => $catalog->id,
            'registration_status' => 'rejected',
        ]);
        $this->createRepresentative('Requested Rep', 'requested-rep@example.com', '01033333333', [
            'requested_company_name' => 'Requested Pharma',
            'registration_status' => 'pending',
            'status' => 'inactive',
        ]);

        $line = Line::create(['name' => 'Cardiology Line']);
        $area = Area::create(['name' => 'Maadi']);
        $managed->lines()->attach($line);
        $managed->areas()->attach($area);

        $this->actingAs($this->createUser('super_admin'))
            ->get('/superadmin/representatives')
            ->assertOk()
            ->assertSee('Managed Rep')
            ->assertSee('Managed Pharma')
            ->assertSee('Catalog Rep')
            ->assertSee('Catalog Pharma')
            ->assertSee('Requested Rep')
            ->assertSee('Requested Pharma')
            ->assertSee('Cardiology Line')
            ->assertSee('Maadi')
            ->assertSee('Rejected')
            ->assertSee('Inactive');
    }

    public function test_search_matches_contact_company_work_details_and_status(): void
    {
        $company = Company::create(['name' => 'Managed Pharma', 'email' => 'managed@example.com', 'password' => 'secret']);
        $catalog = RepCompanyCatalog::create(['name' => 'Catalog Pharma', 'normalized_name' => 'CATALOG PHARMA']);
        $managed = $this->createRepresentative('Alice Managed', 'alice@example.com', '01011111111', [
            'company_id' => $company->id,
        ]);
        $this->createRepresentative('Bob Catalog', 'bob@example.com', '01022222222', [
            'company_catalog_id' => $catalog->id,
            'requested_line_name' => 'Oncology',
            'requested_area_names' => ['Heliopolis'],
        ]);
        $this->createRepresentative('Carol Requested', 'carol@example.com', '01033333333', [
            'requested_company_name' => 'Requested Pharma',
            'registration_status' => 'rejected',
        ]);

        $managed->lines()->attach(Line::create(['name' => 'Cardiology']));
        $managed->areas()->attach(Area::create(['name' => 'Maadi']));

        $this->actingAs($this->createUser('super_admin'));

        foreach ([
            'Alice' => 'Alice Managed',
            'alice@' => 'Alice Managed',
            '111111' => 'Alice Managed',
            'Managed Pharma' => 'Alice Managed',
            'Cardiology' => 'Alice Managed',
            'Maadi' => 'Alice Managed',
            'Catalog Pharma' => 'Bob Catalog',
            'Oncology' => 'Bob Catalog',
            'Heliopolis' => 'Bob Catalog',
            'Requested Pharma' => 'Carol Requested',
            'rejected' => 'Carol Requested',
        ] as $term => $expectedName) {
            $response = $this->get('/superadmin/representatives?search=' . rawurlencode($term));
            $response->assertOk()->assertSee($expectedName);

            foreach (['Alice Managed', 'Bob Catalog', 'Carol Requested'] as $otherName) {
                if ($otherName !== $expectedName) {
                    $response->assertDontSee($otherName);
                }
            }
        }

        $this->get('/superadmin/representatives?search=does-not-exist')
            ->assertOk()
            ->assertSee('No representatives match your search.');
    }

    public function test_search_is_retained_on_later_pages_and_access_is_superadmin_only(): void
    {
        for ($index = 1; $index <= 21; $index++) {
            $this->createRepresentative('Search Rep ' . $index, 'rep-' . $index . '@example.com', '010' . str_pad((string) $index, 8, '0', STR_PAD_LEFT));
        }
        $this->createRepresentative('Unrelated Person', 'unrelated@example.com', '01199999999');

        $this->get('/superadmin/representatives')->assertRedirect(route('login.form'));
        $this->actingAs($this->createUser('admin'))
            ->get('/superadmin/representatives')
            ->assertForbidden();

        $this->actingAs($this->createUser('super_admin'))
            ->get('/superadmin/representatives?search=Search%20Rep')
            ->assertOk()
            ->assertSee('search=Search%20Rep&amp;page=2', false)
            ->assertDontSee('Unrelated Person');

        $this->get('/superadmin/representatives?search=Search%20Rep&page=2')
            ->assertOk()
            ->assertViewHas('representatives', fn ($representatives) => $representatives->currentPage() === 2
                && $representatives->count() === 1
                && $representatives->total() === 21
                && str_starts_with($representatives->first()->name, 'Search Rep'))
            ->assertDontSee('Unrelated Person');
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role . '@example.com',
            'password' => 'secret',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function createRepresentative(string $name, string $email, string $phone, array $attributes = []): Representative
    {
        return Representative::create(array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'registration_status' => 'active',
            'status' => 'active',
        ], $attributes));
    }

    private function createTestingSchema(): void
    {
        foreach (['area_representative', 'line_representative', 'representatives', 'rep_company_catalogs', 'companies', 'areas', 'lines', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('rep_company_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable();
            $table->foreignId('company_catalog_id')->nullable();
            $table->string('requested_company_name')->nullable();
            $table->string('requested_line_name')->nullable();
            $table->json('requested_area_names')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');
            $table->string('registration_status')->default('active');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        foreach (['areas', 'lines'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        foreach (['area_representative', 'line_representative'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedBigInteger('representative_id');
                $table->unsignedBigInteger(str_replace('_representative', '_id', $tableName));
            });
        }
    }
}
