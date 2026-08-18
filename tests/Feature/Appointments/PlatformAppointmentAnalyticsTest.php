<?php

namespace Tests\Feature\Appointments;

use App\Models\User;
use App\Services\AppVersionRemoteConfigService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformAppointmentAnalyticsTest extends TestCase
{
    private const FIRST_CODE = '00000000-0000-0000-0000-000000000001';

    private const SECOND_CODE = '00000000-0000-0000-0000-000000000002';

    private const THIRD_CODE = '00000000-0000-0000-0000-000000000003';

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
        $this->seedAppointments();
    }

    public function test_super_admin_can_see_platform_wide_appointment_analytics(): void
    {
        $response = $this->actingAs($this->createUser('super_admin'))
            ->get(route('appointments.index'));

        $response->assertOk()
            ->assertSee('Appointments &amp; Analytics', false)
            ->assertSee('Total appointments')
            ->assertSee('Completion rate')
            ->assertSee('Completed visits')
            ->assertSee('Completed')
            ->assertSee('Suspended')
            ->assertDontSee('Confirmation rate')
            ->assertDontSee('Confirmed visits')
            ->assertDontSee('Awaiting confirmation')
            ->assertSee('Top doctors')
            ->assertSee('Alpha Doctor')
            ->assertSee('Beta Doctor')
            ->assertSee('Acme Pharma')
            ->assertSee('All appointments');
    }

    public function test_dashboard_shows_the_live_appointment_overview_and_latest_bookings(): void
    {
        $remoteConfig = \Mockery::mock(AppVersionRemoteConfigService::class);
        $remoteConfig->shouldReceive('applyToDashboardValues')
            ->once()
            ->andReturnUsing(fn (array $versions, array $forced) => [$versions, $forced]);
        $this->app->instance(AppVersionRemoteConfigService::class, $remoteConfig);

        $response = $this->actingAs($this->createUser('super_admin'))
            ->get(route('superadmin.dashboard'));

        $response->assertOk()
            ->assertSee('Total Appointments')
            ->assertSee('Completion Rate')
            ->assertSee('Completed Visits')
            ->assertSee('Appointments over the last 30 days')
            ->assertSee('Status overview')
            ->assertSee('Completed')
            ->assertSee('Suspended')
            ->assertDontSee('Confirmation Rate')
            ->assertDontSee('Confirmed Visits')
            ->assertDontSee('Awaiting confirmation')
            ->assertSee('Latest appointments')
            ->assertSee('Alpha Doctor');

        $this->assertSame(
            [1, 3, 2],
            $response->viewData('latestAppointments')->pluck('id')->all()
        );
    }

    public function test_appointments_default_to_newest_created_and_support_all_sort_options(): void
    {
        $admin = $this->createUser('super_admin');

        $this->actingAs($admin)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Newest created')
            ->assertSeeInOrder([self::FIRST_CODE, self::THIRD_CODE, self::SECOND_CODE]);

        $this->get(route('appointments.index', ['sort' => 'created_asc']))
            ->assertOk()
            ->assertSeeInOrder([self::SECOND_CODE, self::THIRD_CODE, self::FIRST_CODE]);

        $this->get(route('appointments.index', ['sort' => 'appointment_desc']))
            ->assertOk()
            ->assertSeeInOrder([self::THIRD_CODE, self::FIRST_CODE, self::SECOND_CODE]);

        $this->get(route('appointments.index', ['sort' => 'appointment_asc']))
            ->assertOk()
            ->assertSeeInOrder([self::SECOND_CODE, self::FIRST_CODE, self::THIRD_CODE]);
    }

    public function test_invalid_appointment_sort_is_rejected(): void
    {
        $this->actingAs($this->createUser('super_admin'))
            ->get(route('appointments.index', ['sort' => 'invalid']))
            ->assertSessionHasErrors('sort');
    }

    public function test_sort_and_filters_are_preserved_in_pagination_links(): void
    {
        $appointments = [];
        for ($id = 4; $id <= 23; $id++) {
            $appointments[] = [
                'id' => $id,
                'doctors_id' => 1,
                'representative_id' => 1,
                'company_id' => 1,
                'date' => now()->toDateString(),
                'start_time' => '13:00:00',
                'end_time' => '13:30:00',
                'status' => 'confirmed',
                'appointment_code' => sprintf('00000000-0000-0000-0000-%012d', $id),
                'created_at' => '2026-08-09 10:00:00',
                'updated_at' => '2026-08-09 10:00:00',
            ];
        }
        DB::table('appointments')->insert($appointments);

        $response = $this->actingAs($this->createUser('super_admin'))
            ->get(route('appointments.index', [
                'status' => 'confirmed',
                'from_date' => now()->toDateString(),
                'sort' => 'created_asc',
            ]));

        $response->assertOk();
        $secondPageUrl = $response->viewData('appointments')->url(2);
        $this->assertStringContainsString('status=confirmed', $secondPageUrl);
        $this->assertStringContainsString('from_date='.now()->toDateString(), $secondPageUrl);
        $this->assertStringContainsString('sort=created_asc', $secondPageUrl);
    }

    public function test_status_date_and_search_filters_limit_appointments_and_analytics(): void
    {
        $admin = $this->createUser('super_admin');

        $statusResponse = $this->actingAs($admin)->get(route('appointments.index', [
            'status' => 'confirmed',
        ]));

        $statusResponse->assertOk()
            ->assertSee('Alpha Doctor')
            ->assertDontSee('Beta Doctor')
            ->assertSee('Showing 1–1 of 1');

        $searchResponse = $this->actingAs($admin)->get(route('appointments.index', [
            'search' => 'Beta Medical',
        ]));

        $searchResponse->assertOk()
            ->assertSee('Beta Doctor')
            ->assertDontSee('Alpha Doctor')
            ->assertSee('Showing 1–1 of 1');
    }

    public function test_company_admin_cannot_access_platform_appointment_analytics(): void
    {
        $this->actingAs($this->createUser('admin'))
            ->get(route('appointments.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_export_the_filtered_appointment_set(): void
    {
        $response = $this->actingAs($this->createUser('super_admin'))
            ->get(route('appointments.export', ['status' => 'pending']));

        $response->assertOk();
        $this->assertStringContainsString('appointments_', (string) $response->headers->get('content-disposition'));
        $content = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Beta Doctor', $content);
        $this->assertStringNotContainsString('Alpha Doctor', $content);
    }

    public function test_export_includes_created_time_and_uses_selected_sort_order(): void
    {
        $response = $this->actingAs($this->createUser('super_admin'))
            ->get(route('appointments.export', ['sort' => 'created_asc']));

        $response->assertOk();
        $content = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Created At', $content);
        $this->assertStringContainsString('2026-08-09 07:00:00', $content);
        $this->assertStringContainsString('2026-08-09 08:00:00', $content);
        $this->assertStringContainsString('2026-08-09 09:00:00', $content);
        $this->assertTrue(
            strpos($content, '2026-08-09 07:00:00') < strpos($content, '2026-08-09 08:00:00')
            && strpos($content, '2026-08-09 08:00:00') < strpos($content, '2026-08-09 09:00:00')
        );
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $role.'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function seedAppointments(): void
    {
        DB::table('specialties')->insert([
            'id' => 1,
            'name' => 'Cardiology',
        ]);
        DB::table('doctors')->insert([
            ['id' => 1, 'name' => 'Alpha Doctor', 'specialty_id' => 1],
            ['id' => 2, 'name' => 'Beta Doctor', 'specialty_id' => 1],
        ]);
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'Acme Pharma'],
            ['id' => 2, 'name' => 'Beta Medical'],
        ]);
        DB::table('representatives')->insert([
            ['id' => 1, 'company_id' => 1, 'name' => 'Alice Rep', 'phone' => '01000000001'],
            ['id' => 2, 'company_id' => 2, 'name' => 'Bob Rep', 'phone' => '01000000002'],
        ]);

        $today = now()->toDateString();
        DB::table('appointments')->insert([
            [
                'doctors_id' => 1,
                'representative_id' => 1,
                'company_id' => 1,
                'date' => $today,
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'status' => 'confirmed',
                'appointment_code' => self::FIRST_CODE,
                'created_at' => '2026-08-09 09:00:00',
                'updated_at' => '2026-08-09 09:00:00',
            ],
            [
                'doctors_id' => 1,
                'representative_id' => 1,
                'company_id' => 1,
                'date' => now()->subDay()->toDateString(),
                'start_time' => '11:00:00',
                'end_time' => '11:30:00',
                'status' => 'cancelled',
                'appointment_code' => self::SECOND_CODE,
                'created_at' => '2026-08-09 07:00:00',
                'updated_at' => '2026-08-09 07:00:00',
            ],
            [
                'doctors_id' => 2,
                'representative_id' => 2,
                'company_id' => 2,
                'date' => now()->addDay()->toDateString(),
                'start_time' => '12:00:00',
                'end_time' => '12:30:00',
                'status' => 'pending',
                'appointment_code' => self::THIRD_CODE,
                'created_at' => '2026-08-09 08:00:00',
                'updated_at' => '2026-08-09 08:00:00',
            ],
        ]);
    }

    private function createTestingSchema(): void
    {
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
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('specialty_id')->nullable();
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('rep_company_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id')->nullable();
            $table->unsignedBigInteger('representative_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('company_catalog_id')->nullable();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('status')->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });
        Schema::create('feedback_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email_feedback')->nullable();
            $table->timestamps();
        });
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app_type');
            $table->string('platform');
            $table->string('version');
            $table->boolean('is_forced')->default(false);
            $table->timestamps();
        });
    }
}
