<?php

namespace Tests\Feature\Admin;

use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuperadminDoctorsSearchTest extends TestCase
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

    public function test_search_finds_doctor_profile_and_availability_fields(): void
    {
        $cardiology = Specialty::create(['name' => 'Cardiology']);
        $orthopedics = Specialty::create(['name' => 'Orthopedics']);
        $target = $this->createDoctor('Alice Doctor', 'alice@example.com', '01011111111', $cardiology->id, [
            'address_1' => 'Maadi Clinic',
            'address_2' => 'Nile Street',
            'status' => 'busy',
            'from_date' => '2026-12-01',
            'to_date' => '2026-12-31',
        ]);
        $this->createDoctor('Bob Doctor', 'bob@example.com', '01022222222', $orthopedics->id, [
            'address_1' => 'Giza Clinic',
            'status' => 'active',
        ]);
        DoctorAvailability::create([
            'doctors_id' => $target->id,
            'date' => 'thursday',
            'start_time' => '13:30:00',
            'end_time' => '14:30:00',
            'max_reps_per_range' => 17,
            'visit_time_type' => 'before',
            'status' => 'canceled',
        ]);

        $this->actingAs($this->createSuperadmin());

        foreach ([
            'Alice',
            'alice@',
            '111111',
            'Maadi',
            'Nile Street',
            'Cardiology',
            'busy',
            '2026-12-01',
            '2026-12-31',
            'thursday',
            '13:30',
            '14:30',
            'canceled',
            'before',
            '17',
        ] as $term) {
            $this->get('/superadmin/doctors?search=' . rawurlencode($term))
                ->assertOk()
                ->assertSee('Alice Doctor')
                ->assertDontSee('Bob Doctor');
        }

        $this->get('/superadmin/doctors?search=missing-value')
            ->assertOk()
            ->assertSee('No doctors match your search.');

    }

    public function test_search_preserves_pagination_and_existing_actions(): void
    {
        $specialty = Specialty::create(['name' => 'Dermatology']);

        for ($index = 1; $index <= 11; $index++) {
            $this->createDoctor('Search Doctor ' . $index, 'doctor-' . $index . '@example.com',
                '010' . str_pad((string) $index, 8, '0', STR_PAD_LEFT), $specialty->id);
        }
        $this->createDoctor('Unrelated Doctor', 'unrelated@example.com', '01199999999', $specialty->id);

        $this->actingAs($this->createSuperadmin())
            ->get('/superadmin/doctors?search=Search%20Doctor')
            ->assertOk()
            ->assertSee('search=Search%20Doctor&amp;page=2', false)
            ->assertSee('Add Doctor')
            ->assertDontSee('Unrelated Doctor');

        $this->get('/superadmin/doctors?search=Search%20Doctor&page=2')
            ->assertOk()
            ->assertViewHas('doctors', fn ($doctors) => $doctors->currentPage() === 2
                && $doctors->count() === 1
                && $doctors->total() === 11
                && str_starts_with($doctors->first()->name, 'Search Doctor'))
            ->assertDontSee('Unrelated Doctor');
    }

    private function createSuperadmin(): User
    {
        return User::create([
            'name' => 'Superadmin',
            'email' => 'superadmin@example.com',
            'password' => 'secret',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function createDoctor(string $name, string $email, string $phone, int $specialtyId, array $attributes = []): Doctors
    {
        return Doctors::create(array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'specialty_id' => $specialtyId,
            'status' => 'active',
        ], $attributes));
    }

    private function createTestingSchema(): void
    {
        foreach (['doctor_availabilities', 'doctors', 'specialties', 'users'] as $table) {
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

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('status')->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->timestamps();
        });

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('max_reps_per_range')->nullable();
            $table->string('visit_time_type')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
        });
    }
}
