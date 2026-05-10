<?php

namespace Tests\Feature\AppVersions;

use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Contract\RemoteConfig;
use Kreait\Firebase\RemoteConfig\Template;
use Mockery;
use Tests\TestCase;

class AppVersionPlatformFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createTestingSchema();
    }

    public function test_dashboard_save_writes_company_and_doctor_platform_rules(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->bindRemoteConfig([], function (Template $template): bool {
            $parameters = $template->parameters();

            return $this->templateValue($parameters, 'AppVersionCompAndroid') === '1.0.1'
                && $this->templateValue($parameters, 'IsForceUpdateCompAndroid') === 'true'
                && $this->templateValue($parameters, 'AppVersionCompIos') === '1.0.2'
                && $this->templateValue($parameters, 'IsForceUpdateCompIos') === 'false'
                && $this->templateValue($parameters, 'AppVersionDrAndroid') === '2.0.1'
                && $this->templateValue($parameters, 'IsForceUpdateDrAndroid') === 'false'
                && $this->templateValue($parameters, 'AppVersionDrIos') === '2.0.2'
                && $this->templateValue($parameters, 'IsForceUpdateDrIos') === 'true';
        });

        $payload = [
            'apps' => [
                'company' => [
                    'both' => ['version' => '1.0.0', 'is_forced' => '0'],
                    'android' => ['version' => '1.0.1', 'is_forced' => '1'],
                    'ios' => ['version' => '1.0.2', 'is_forced' => '0'],
                ],
                'doctor' => [
                    'both' => ['version' => '2.0.0', 'is_forced' => '0'],
                    'android' => ['version' => '2.0.1', 'is_forced' => '0'],
                    'ios' => ['version' => '2.0.2', 'is_forced' => '1'],
                ],
            ],
        ];

        $response = $this
            ->actingAs($admin)
            ->post(route('superadmin.app.versions'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'company',
            'platform' => 'both',
            'version' => '1.0.0',
            'is_forced' => 0,
        ]);

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'company',
            'platform' => 'android',
            'version' => '1.0.1',
            'is_forced' => 1,
        ]);

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'company',
            'platform' => 'ios',
            'version' => '1.0.2',
            'is_forced' => 0,
        ]);

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'doctor',
            'platform' => 'both',
            'version' => '2.0.0',
            'is_forced' => 0,
        ]);

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'doctor',
            'platform' => 'android',
            'version' => '2.0.1',
            'is_forced' => 0,
        ]);

        $this->assertDatabaseHas('app_versions', [
            'app_type' => 'doctor',
            'platform' => 'ios',
            'version' => '2.0.2',
            'is_forced' => 1,
        ]);
    }

    public function test_dashboard_save_rejects_missing_required_platform_section(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->bindRemoteConfig();

        $payload = [
            'apps' => [
                'company' => [
                    'both' => ['version' => '1.0.0', 'is_forced' => '0'],
                    'android' => ['version' => '1.0.1', 'is_forced' => '1'],
                ],
                'doctor' => [
                    'both' => ['version' => '2.0.0', 'is_forced' => '0'],
                    'android' => ['version' => '2.0.1', 'is_forced' => '0'],
                    'ios' => ['version' => '2.0.2', 'is_forced' => '1'],
                ],
            ],
        ];

        $response = $this
            ->actingAs($admin)
            ->from(route('superadmin.dashboard'))
            ->post(route('superadmin.app.versions'), $payload);

        $response->assertSessionHasErrors(['apps.company.ios']);
    }

    public function test_check_version_returns_platform_specific_rule_for_company_when_available(): void
    {
        $this->bindRemoteConfig([
            'AppVersionCompAndroid' => '9.1.0',
            'IsForceUpdateCompAndroid' => 'true',
        ]);

        AppVersion::insert([
            [
                'app_type' => 'company',
                'platform' => 'both',
                'version' => '1.0.0',
                'is_forced' => 0,
            ],
            [
                'app_type' => 'company',
                'platform' => 'android',
                'version' => '1.1.0',
                'is_forced' => 1,
            ],
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'company',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '9.1.0');
        $response->assertJsonPath('data.isForced', true);
        $response->assertJsonPath('data.platform', 'android');
    }

    public function test_check_version_reads_company_ios_rule_from_firebase(): void
    {
        $this->bindRemoteConfig([
            'AppVersionCompIos' => '9.2.0',
            'IsForceUpdateCompIos' => 'false',
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'company',
            'platform' => 'ios',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '9.2.0');
        $response->assertJsonPath('data.isForced', false);
        $response->assertJsonPath('data.platform', 'ios');
    }

    public function test_check_version_reads_doctor_android_rule_from_firebase(): void
    {
        $this->bindRemoteConfig([
            'AppVersionDrAndroid' => '8.1.0',
            'IsForceUpdateDrAndroid' => 'yes',
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'doctor',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '8.1.0');
        $response->assertJsonPath('data.isForced', true);
        $response->assertJsonPath('data.platform', 'android');
    }

    public function test_check_version_reads_doctor_ios_rule_from_firebase(): void
    {
        $this->bindRemoteConfig([
            'AppVersionDrIos' => '8.2.0',
            'IsForceUpdateDrIos' => '0',
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'doctor',
            'platform' => 'ios',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '8.2.0');
        $response->assertJsonPath('data.isForced', false);
        $response->assertJsonPath('data.platform', 'ios');
    }

    public function test_check_version_falls_back_to_both_when_requested_platform_is_missing(): void
    {
        $this->bindRemoteConfig();

        AppVersion::create([
            'app_type' => 'doctor',
            'platform' => 'both',
            'version' => '2.0.0',
            'is_forced' => 0,
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'doctor',
            'platform' => 'ios',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '2.0.0');
        $response->assertJsonPath('data.isForced', false);
        $response->assertJsonPath('data.platform', 'both');
    }

    public function test_check_version_without_platform_uses_both_rule(): void
    {
        $this->bindRemoteConfig();

        AppVersion::insert([
            [
                'app_type' => 'company',
                'platform' => 'both',
                'version' => '1.2.0',
                'is_forced' => 0,
            ],
            [
                'app_type' => 'company',
                'platform' => 'android',
                'version' => '1.3.0',
                'is_forced' => 1,
            ],
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'company',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '1.2.0');
        $response->assertJsonPath('data.platform', 'both');
    }

    public function test_check_version_rejects_invalid_app_type(): void
    {
        $this->bindRemoteConfig();

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'super_admin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['app_type']);
    }

    public function test_check_version_falls_back_to_db_when_firebase_parameter_is_missing(): void
    {
        $this->bindRemoteConfig([
            'IsForceUpdateCompAndroid' => 'true',
        ]);

        AppVersion::create([
            'app_type' => 'company',
            'platform' => 'android',
            'version' => '3.0.0',
            'is_forced' => 0,
        ]);

        $response = $this->postJson('/api/check-version', [
            'app_type' => 'company',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.version', '3.0.0');
        $response->assertJsonPath('data.isForced', false);
        $response->assertJsonPath('data.platform', 'android');
    }

    private function createTestingSchema(): void
    {
        Schema::dropIfExists('app_versions');
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

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app_type');
            $table->string('platform')->default(AppVersion::PLATFORM_BOTH);
            $table->string('version');
            $table->boolean('is_forced')->default(false);
            $table->string('store_url')->nullable();
            $table->timestamps();
            $table->unique(['app_type', 'platform'], 'app_versions_app_type_platform_unique');
        });
    }

    private function bindRemoteConfig(array $parameters = [], ?callable $publishAssertion = null): void
    {
        $mock = Mockery::mock(RemoteConfig::class);
        $mock->shouldReceive('get')
            ->zeroOrMoreTimes()
            ->andReturn(Template::fromArray(['parameters' => $this->remoteConfigParameters($parameters)]));

        if ($publishAssertion) {
            $mock->shouldReceive('publish')
                ->once()
                ->with(Mockery::on($publishAssertion))
                ->andReturn('etag');
        } else {
            $mock->shouldReceive('publish')->zeroOrMoreTimes()->andReturn('etag');
        }

        $this->app->instance(RemoteConfig::class, $mock);
    }

    private function remoteConfigParameters(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): array => ['defaultValue' => ['value' => $value]])
            ->all();
    }

    private function templateValue(array $parameters, string $name): ?string
    {
        $parameter = $parameters[$name] ?? null;

        if (!$parameter || !$parameter->defaultValue()) {
            return null;
        }

        return $parameter->defaultValue()->toArray()['value'] ?? null;
    }
}
