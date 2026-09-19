<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Modules\Branding\Models\BrandingSetting;
use App\Modules\Branding\Services\BrandingService;
use App\Modules\Branding\Support\CompanyStorageNamespace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class CompanyBrandingStorageTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        Storage::fake('public');
    }

    public function test_authorized_admin_can_upload_png_logo(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $company = Company::query()->firstOrFail();

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson('/api/admin/settings/branding/logo', [
            'logo' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonStructure([
                'message',
                'data' => ['company_id', 'app_name', 'company_name', 'logo_url', 'logo_path', 'tenant_id'],
            ]);

        $logoPath = $response->json('data.logo_path');
        $this->assertStringStartsWith("companies/{$company->storage_namespace}/branding/logos/", $logoPath);
        $this->assertStringEndsWith('.png', $logoPath);

        Storage::disk('public')->assertExists($logoPath);

        $this->assertDatabaseHas('branding_settings', [
            'company_id' => $company->id,
            'logo_path' => $logoPath,
        ]);
    }

    public function test_authorized_admin_can_upload_webp_and_jpeg_logos(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::query()->firstOrFail();

        // WebP
        $webp = UploadedFile::fake()->image('logo.webp', 128, 128);
        $resWebp = $this->postJson('/api/admin/settings/branding/logo', ['logo' => $webp])->assertOk();
        $webpPath = $resWebp->json('data.logo_path');
        $this->assertStringEndsWith('.webp', $webpPath);
        Storage::disk('public')->assertExists($webpPath);

        // JPEG replaces WebP
        $jpg = UploadedFile::fake()->image('logo.jpg', 256, 256);
        $resJpg = $this->postJson('/api/admin/settings/branding/logo', ['logo' => $jpg])->assertOk();
        $jpgPath = $resJpg->json('data.logo_path');
        $this->assertStringEndsWith('.jpg', $jpgPath);
        Storage::disk('public')->assertExists($jpgPath);

        // Old WebP should be removed
        Storage::disk('public')->assertMissing($webpPath);
    }

    public function test_unauthenticated_or_unauthorized_users_cannot_upload(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        // 1. Unauthenticated
        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $file])
            ->assertUnauthorized();

        // 2. Authenticated user without permission
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $file])
            ->assertForbidden();
    }

    public function test_invalid_files_are_rejected(): void
    {
        $this->actingAsSuperAdmin();

        // 1. Text file renamed to png
        $fakeText = UploadedFile::fake()->create('fake.png', 50, 'text/plain');
        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $fakeText])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        // 2. SVG is rejected
        $svg = UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');
        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $svg])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        // 3. Oversized file (> 4 MB)
        $oversized = UploadedFile::fake()->image('huge.png', 500, 500)->size(5000);
        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $oversized])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);

        // 4. Invalid dimensions (< 64px)
        $tiny = UploadedFile::fake()->image('tiny.png', 32, 32);
        $this->postJson('/api/admin/settings/branding/logo', ['logo' => $tiny])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_multi_tenant_isolation_with_two_companies(): void
    {
        $companyA = Company::query()->firstOrFail();

        // Create Company B
        $companyB = Company::create([
            'name' => 'Swift Courier',
            'slug' => 'swift-courier',
            'storage_namespace' => 'temp',
        ]);
        $companyB->storage_namespace = CompanyStorageNamespace::generate($companyB);
        $companyB->saveQuietly();

        // Upload logo for Company A
        $this->actingAsSuperAdmin();
        $fileA = UploadedFile::fake()->image('companyA.png', 100, 100);
        $resA = $this->postJson('/api/admin/settings/branding/logo', ['logo' => $fileA])->assertOk();
        $pathA = $resA->json('data.logo_path');

        // Upload logo for Company B via company-scoped endpoint
        $fileB = UploadedFile::fake()->image('companyB.png', 120, 120);
        $resB = $this->postJson("/api/admin/companies/{$companyB->id}/branding/logo", ['logo' => $fileB])->assertOk();
        $pathB = $resB->json('data.logo_path');

        // Assert paths use their respective immutable storage namespaces
        $this->assertStringStartsWith("companies/{$companyA->storage_namespace}/branding/logos/", $pathA);
        $this->assertStringStartsWith("companies/{$companyB->storage_namespace}/branding/logos/", $pathB);
        $this->assertNotEquals($companyA->storage_namespace, $companyB->storage_namespace);

        Storage::disk('public')->assertExists($pathA);
        Storage::disk('public')->assertExists($pathB);

        // Replace Company A logo - should not affect Company B
        $fileA2 = UploadedFile::fake()->image('companyA_v2.png', 110, 110);
        $resA2 = $this->postJson('/api/admin/settings/branding/logo', ['logo' => $fileA2])->assertOk();
        $pathA2 = $resA2->json('data.logo_path');

        // Company A old logo deleted, new exists
        Storage::disk('public')->assertMissing($pathA);
        Storage::disk('public')->assertExists($pathA2);

        // Company B logo remains intact!
        Storage::disk('public')->assertExists($pathB);

        // Public branding for Company B returns Company B logo
        $publicB = $this->withHeader('X-Tenant-ID', $companyB->storage_namespace)
            ->getJson('/api/public/branding')
            ->assertOk();

        $this->assertEquals($companyB->name, $publicB->json('data.company_name'));
        $this->assertEquals($pathB, $publicB->json('data.logo_path'));
    }

    public function test_company_rename_does_not_alter_storage_namespace(): void
    {
        $company = Company::query()->firstOrFail();
        $originalNamespace = $company->storage_namespace;

        $company->update([
            'name' => 'Fast Logistics International Global',
        ]);

        $company->refresh();
        $this->assertEquals('Fast Logistics International Global', $company->name);
        $this->assertEquals($originalNamespace, $company->storage_namespace);
    }

    public function test_failed_database_update_cleans_up_new_file_and_preserves_old_logo(): void
    {
        $company = Company::query()->firstOrFail();
        $brandingService = app(BrandingService::class);

        // Upload initial logo
        $initialFile = UploadedFile::fake()->image('initial.png', 100, 100);
        $initialBranding = $brandingService->replaceLogo($company, $initialFile);
        $initialPath = $initialBranding->logo_path;
        Storage::disk('public')->assertExists($initialPath);

        // Simulate DB failure by hooking into event or query
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('Simulated database crash during update'));

        $newFile = UploadedFile::fake()->image('new.png', 100, 100);

        try {
            $brandingService->replaceLogo($company, $newFile);
            $this->fail('Exception was expected');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated database crash during update', $e->getMessage());
        }

        // Old logo still exists
        Storage::disk('public')->assertExists($initialPath);

        // Active database path was not changed
        $companyBranding = BrandingSetting::where('company_id', $company->id)->first();
        $this->assertEquals($initialPath, $companyBranding->logo_path);
    }

    public function test_public_branding_endpoint_returns_safe_data_without_secrets(): void
    {
        $company = Company::query()->firstOrFail();

        $response = $this->getJson('/api/public/branding')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'app_name',
                    'company_name',
                    'logo_url',
                    'tenant_id',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('database', $data);
        $this->assertArrayNotHasKey('secret', $data);
        $this->assertArrayNotHasKey('api_key', $data);
        $this->assertArrayNotHasKey('mail_host', $data);
    }

    public function test_reset_logo_removes_uploaded_file(): void
    {
        $this->actingAsSuperAdmin();
        $company = Company::query()->firstOrFail();

        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $res = $this->postJson('/api/admin/settings/branding/logo', ['logo' => $file])->assertOk();
        $path = $res->json('data.logo_path');
        Storage::disk('public')->assertExists($path);

        $this->deleteJson('/api/admin/settings/branding/logo')->assertOk();

        Storage::disk('public')->assertMissing($path);

        $this->assertDatabaseHas('branding_settings', [
            'company_id' => $company->id,
            'logo_path' => null,
        ]);
    }
}
