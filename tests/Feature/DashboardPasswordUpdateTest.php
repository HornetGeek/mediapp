<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardPasswordUpdateTest extends TestCase
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

    public function test_admin_can_open_password_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/dashboard/password');

        $response->assertStatus(200)
            ->assertSee('Change Password');
    }

    public function test_admin_can_update_own_password(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put('/dashboard/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/password');

        $this->assertTrue(Hash::check('new-password', $admin->refresh()->password));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_current_password_does_not_update_password(): void
    {
        $admin = $this->createAdmin();
        $oldPasswordHash = $admin->password;

        $response = $this->actingAs($admin)->from('/dashboard/password')->put('/dashboard/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('current_password')
            ->assertRedirect('/dashboard/password');

        $admin->refresh();

        $this->assertSame($oldPasswordHash, $admin->password);
        $this->assertFalse(Hash::check('new-password', $admin->password));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/password');

        $response->assertRedirect(route('login.form'));
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Dashboard Admin',
            'email' => 'dashboard-admin@example.com',
            'password' => Hash::make('old-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function createTestingSchema(): void
    {
        Schema::dropIfExists('users');

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
    }
}
