<?php

namespace Tests\Feature\Subscriptions;

use App\Events\SendNotificationEvent;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionFlowsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestingSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_company_service_uses_calendar_plan_when_creating_company(): void
    {
        Carbon::setTestNow('2026-01-10 10:00:00');

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $response = app(CompanyService::class)->createCompany([
            'name' => 'Acme',
            'email' => 'acme@example.com',
            'password' => 'secret123',
            'visits_per_day' => 10,
            'num_of_reps' => 5,
            'phone' => '01000000001',
            'package_id' => $package->id,
        ], $package);

        $company = Company::firstOrFail();

        $this->assertSame(200, $response->status());
        $this->assertSame('2026-01-10', Carbon::parse($company->subscription_start)->toDateString());
        $this->assertSame('2026-04-10', Carbon::parse($company->subscription_end)->toDateString());
    }

    public function test_company_service_keeps_custom_day_fallback_for_legacy_packages(): void
    {
        Carbon::setTestNow('2026-01-10 10:00:00');

        $package = Package::create([
            'name' => 'Legacy 45',
            'price' => 450,
            'duration' => 45,
            'plan_type' => Package::PLAN_CUSTOM_DAYS,
            'billing_months' => null,
        ]);

        app(CompanyService::class)->createCompany([
            'name' => 'Legacy Co',
            'email' => 'legacy@example.com',
            'password' => 'secret123',
            'visits_per_day' => 5,
            'num_of_reps' => 2,
            'phone' => '01000000002',
            'package_id' => $package->id,
        ], $package);

        $company = Company::firstOrFail();
        $this->assertSame('2026-02-24', Carbon::parse($company->subscription_end)->toDateString());
    }

    public function test_dashboard_update_recomputes_subscription_end_from_selected_start_date(): void
    {
        Carbon::setTestNow('2026-01-01 08:00:00');

        $admin = User::factory()->create(['role' => 'super_admin']);

        $oldPackage = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $newPackage = Package::create([
            'name' => 'Annual',
            'price' => 3600,
            'duration' => 365,
            'plan_type' => Package::PLAN_ANNUAL,
            'billing_months' => 12,
        ]);

        $company = Company::create([
            'name' => 'Test Co',
            'package_id' => $oldPackage->id,
            'phone' => '01000000003',
            'email' => 'testco@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 4,
            'subscription_start' => '2025-11-01',
            'subscription_end' => '2026-01-30',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($admin)
            ->put(route('companies.update', $company->id), [
                'name' => 'Test Co',
                'package_id' => $newPackage->id,
                'phone' => '01000000003',
                'email' => 'testco@example.com',
                'visits_per_day' => 10,
                'num_of_reps' => 4,
                'subscription_start' => '2026-02-15',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('companies.index'));

        $company->refresh();
        $this->assertSame('2026-02-15', Carbon::parse($company->subscription_start)->toDateString());
        $this->assertSame('2027-02-15', Carbon::parse($company->subscription_end)->toDateString());
    }

    public function test_dashboard_package_form_rejects_missing_plan_type(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $response = $this
            ->actingAs($admin)
            ->post(route('packages.store'), [
                'name' => 'No Plan',
                'price' => 1000,
            ]);

        $response->assertSessionHasErrors(['plan_type']);
    }

    public function test_super_admin_api_create_package_accepts_new_plan_type_contract(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin, ['super-admin']);

        $response = $this
            ->postJson('/api/super-admin/create-package', [
                'name' => 'API Quarterly',
                'price' => 1300,
                'plan_type' => 'quarterly',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('packages', [
            'name' => 'API Quarterly',
            'plan_type' => 'quarterly',
            'billing_months' => 3,
            'duration' => 90,
        ]);
    }

    public function test_super_admin_api_create_package_accepts_legacy_duration_payload(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin, ['super-admin']);

        $response = $this
            ->postJson('/api/super-admin/create-package', [
                'name' => 'API Legacy',
                'price' => 900,
                'duration' => 120,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('packages', [
            'name' => 'API Legacy',
            'plan_type' => 'custom_days',
            'billing_months' => null,
            'duration' => 120,
        ]);
    }

    public function test_subscription_expiry_command_deactivates_company_and_cancels_appointments(): void
    {
        Event::fake([SendNotificationEvent::class]);

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $company = Company::create([
            'name' => 'Expired Co',
            'package_id' => $package->id,
            'phone' => '01000000004',
            'email' => 'expired@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => now()->subMonths(4)->toDateString(),
            'subscription_end' => now()->subDay()->toDateString(),
            'status' => 'active',
            'fcm_token' => 'token-company',
        ]);

        $doctor = Doctors::create([
            'name' => 'Doctor One',
            'email' => 'doctor1@example.com',
            'phone' => '01000000100',
            'password' => Hash::make('secret123'),
            'fcm_token' => 'token-doctor',
        ]);

        $rep = Representative::create([
            'name' => 'Rep One',
            'email' => 'rep1@example.com',
            'phone' => '01000000200',
            'password' => Hash::make('secret123'),
            'company_id' => $company->id,
            'fcm_token' => 'token-rep',
        ]);

        $appointment = Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => 'pending',
        ]);

        Artisan::call('companies:check-subscriptions');

        $company->refresh();
        $appointment->refresh();

        $this->assertSame('inactive', $company->status);
        $this->assertSame('cancelled', $appointment->status);
        $this->assertSame('system', $appointment->cancelled_by);
        Event::assertDispatchedTimes(SendNotificationEvent::class, 2);
    }

    public function test_subscription_reminder_command_targets_companies_expiring_within_15_days(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');
        Event::fake([SendNotificationEvent::class]);

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $targetCompany = Company::create([
            'name' => 'Target Co',
            'package_id' => $package->id,
            'phone' => '01000000005',
            'email' => 'target@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => '2026-03-01',
            'subscription_end' => '2026-06-16',
            'status' => 'active',
            'fcm_token' => 'target-token',
        ]);

        Company::create([
            'name' => 'Out Of Range',
            'package_id' => $package->id,
            'phone' => '01000000006',
            'email' => 'out@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => '2026-03-01',
            'subscription_end' => '2026-06-25',
            'status' => 'active',
            'fcm_token' => 'other-token',
        ]);

        Company::create([
            'name' => 'No Token',
            'package_id' => $package->id,
            'phone' => '01000000007',
            'email' => 'notoken@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => '2026-03-01',
            'subscription_end' => '2026-06-16',
            'status' => 'active',
            'fcm_token' => null,
        ]);

        Artisan::call('app:send-reminder-subscription-for-company');

        Event::assertDispatched(SendNotificationEvent::class, function (SendNotificationEvent $event) use ($targetCompany) {
            return $event->notifiable->id === $targetCompany->id
                && $event->target_type === 'company';
        });
        Event::assertDispatchedTimes(SendNotificationEvent::class, 1);
    }

    public function test_subscription_reminder_is_only_sent_on_exact_day_minus_fifteen(): void
    {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        Company::create([
            'name' => 'One-Time Reminder Co',
            'package_id' => $package->id,
            'phone' => '01000000008',
            'email' => 'one-time@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => '2026-03-01',
            'subscription_end' => '2026-06-16',
            'status' => 'active',
            'fcm_token' => 'one-time-token',
        ]);

        Event::fake([SendNotificationEvent::class]);
        Artisan::call('app:send-reminder-subscription-for-company');
        Event::assertDispatchedTimes(SendNotificationEvent::class, 1);

        Carbon::setTestNow('2026-06-02 10:00:00');
        Event::fake([SendNotificationEvent::class]);
        Artisan::call('app:send-reminder-subscription-for-company');
        Event::assertNotDispatched(SendNotificationEvent::class);

        Carbon::setTestNow('2026-06-03 10:00:00');
        Event::fake([SendNotificationEvent::class]);
        Artisan::call('app:send-reminder-subscription-for-company');
        Event::assertNotDispatched(SendNotificationEvent::class);
    }

    public function test_subscription_expiry_command_sends_summary_notifications_per_user_not_per_appointment(): void
    {
        Event::fake([SendNotificationEvent::class]);

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1200,
            'duration' => 90,
            'plan_type' => Package::PLAN_QUARTERLY,
            'billing_months' => 3,
        ]);

        $company = Company::create([
            'name' => 'Summary Co',
            'package_id' => $package->id,
            'phone' => '01000000009',
            'email' => 'summary@example.com',
            'password' => Hash::make('secret123'),
            'visits_per_day' => 10,
            'num_of_reps' => 3,
            'subscription_start' => now()->subMonths(4)->toDateString(),
            'subscription_end' => now()->subDay()->toDateString(),
            'status' => 'active',
            'fcm_token' => 'summary-company-token',
        ]);

        $doctor = Doctors::create([
            'name' => 'Doctor Summary',
            'email' => 'doctor-summary@example.com',
            'phone' => '01000000101',
            'password' => Hash::make('secret123'),
            'fcm_token' => 'summary-doctor-token',
        ]);

        $rep = Representative::create([
            'name' => 'Rep Summary',
            'email' => 'rep-summary@example.com',
            'phone' => '01000000201',
            'password' => Hash::make('secret123'),
            'company_id' => $company->id,
            'fcm_token' => 'summary-rep-token',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => 'pending',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'pending',
        ]);

        Artisan::call('companies:check-subscriptions');

        Event::assertDispatchedTimes(SendNotificationEvent::class, 2);
        Event::assertDispatched(SendNotificationEvent::class, function (SendNotificationEvent $event) use ($doctor) {
            return $event->target_type === 'doctor'
                && $event->notifiable->id === $doctor->id
                && str_contains($event->body, '2 visit(s)');
        });
        Event::assertDispatched(SendNotificationEvent::class, function (SendNotificationEvent $event) use ($rep) {
            return $event->target_type === 'reps'
                && $event->notifiable->id === $rep->id
                && str_contains($event->body, '2 of your visit(s)');
        });
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'appointments',
            'representatives',
            'doctors',
            'companies',
            'packages',
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

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('duration');
            $table->string('plan_type')->default(Package::PLAN_CUSTOM_DAYS);
            $table->unsignedTinyInteger('billing_months')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
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

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });
    }
}
