<?php

namespace Tests\Feature\BannerAds;

use App\Models\BannerAd;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerAdsManagementTest extends TestCase
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

        Storage::fake('public');
        $this->createTestingSchema();
    }

    public function test_super_admin_can_create_update_and_delete_banner_ad_with_image_files(): void
    {
        $admin = $this->createSuperAdmin();

        $createResponse = $this->actingAs($admin)->post('/superadmin/banner-ads/store', [
            'title' => 'Home Promo',
            'image' => UploadedFile::fake()->image('banner.jpg', 1200, 480),
            'click_url' => 'https://example.com/promo',
            'sort_order' => 2,
            'status' => 'active',
        ]);

        $createResponse->assertRedirect(route('banner-ads.index'));

        $bannerAd = BannerAd::firstOrFail();
        Storage::disk('public')->assertExists($bannerAd->image_path);
        $oldImagePath = $bannerAd->image_path;

        $updateResponse = $this->actingAs($admin)->post('/superadmin/banner-ads/update/' . $bannerAd->id, [
            '_method' => 'PUT',
            'title' => 'Updated Promo',
            'image' => UploadedFile::fake()->image('banner.webp', 1200, 480),
            'click_url' => 'https://example.com/updated',
            'sort_order' => 1,
            'status' => 'inactive',
        ]);

        $updateResponse->assertRedirect(route('banner-ads.index'));

        $bannerAd->refresh();
        $this->assertSame('Updated Promo', $bannerAd->title);
        $this->assertSame('inactive', $bannerAd->status);
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($bannerAd->image_path);
        $newImagePath = $bannerAd->image_path;

        $deleteResponse = $this->actingAs($admin)->get('/superadmin/banner-ads/delete/' . $bannerAd->id);

        $deleteResponse->assertRedirect(route('banner-ads.index'));
        $this->assertDatabaseMissing('banner_ads', ['id' => $bannerAd->id]);
        Storage::disk('public')->assertMissing($newImagePath);
    }

    public function test_create_requires_valid_image(): void
    {
        $admin = $this->createSuperAdmin();

        $missingImageResponse = $this->actingAs($admin)
            ->from('/superadmin/banner-ads')
            ->post('/superadmin/banner-ads/store', [
                'title' => 'Missing Image',
                'sort_order' => 0,
                'status' => 'active',
            ]);

        $missingImageResponse->assertRedirect('/superadmin/banner-ads');
        $missingImageResponse->assertSessionHasErrors('image');

        $invalidImageResponse = $this->actingAs($admin)
            ->from('/superadmin/banner-ads')
            ->post('/superadmin/banner-ads/store', [
                'title' => 'Invalid Image',
                'image' => UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'),
                'sort_order' => 0,
                'status' => 'active',
            ]);

        $invalidImageResponse->assertRedirect('/superadmin/banner-ads');
        $invalidImageResponse->assertSessionHasErrors('image');
    }

    public function test_public_api_returns_active_banners_in_sort_order(): void
    {
        $inactive = BannerAd::create([
            'title' => 'Inactive',
            'image_path' => 'banner-ads/inactive.jpg',
            'sort_order' => 1,
            'status' => 'inactive',
        ]);
        $second = BannerAd::create([
            'title' => 'Second',
            'image_path' => 'banner-ads/second.jpg',
            'click_url' => 'https://example.com/second',
            'sort_order' => 2,
            'status' => 'active',
        ]);
        $first = BannerAd::create([
            'title' => 'First',
            'image_path' => 'banner-ads/first.jpg',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/banner-ads');

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Banner ads retrieved successfully')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.title', 'First')
            ->assertJsonPath('data.0.image_url', '/storage/banner-ads/first.jpg')
            ->assertJsonPath('data.0.click_url', null)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.click_url', 'https://example.com/second');

        $this->assertNotContains($inactive->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_public_disk_url_trims_trailing_app_url_slash(): void
    {
        config([
            'filesystems.disks.public.url' => rtrim('https://mediapps.online/', '/') . '/storage',
        ]);
        Storage::forgetDisk('public');

        $bannerAd = new BannerAd([
            'title' => 'Promo',
            'image_path' => 'banner-ads/promo.jpg',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $this->assertSame(
            'https://mediapps.online/storage/banner-ads/promo.jpg',
            $bannerAd->image_url
        );
        $this->assertStringNotContainsString('online//storage', $bannerAd->image_url);
    }

    private function createSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function createTestingSchema(): void
    {
        foreach (['banner_ads', 'users', 'sessions'] as $table) {
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

        Schema::create('banner_ads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_path');
            $table->string('click_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }
}
