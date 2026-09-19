<?php

namespace App\Modules\Branding\Services;

use App\Models\Company;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Branding\Contracts\CompanyFileStorage;
use App\Modules\Branding\Models\BrandingSetting;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BrandingService
{
    public function __construct(
        private readonly CompanyFileStorage $fileStorage = new TenantFileStorageService(),
        private readonly ?AuditLogService $auditLogService = null,
        private readonly ?SystemSettingService $systemSettingService = null,
    ) {
    }

    public function getOrCreateBranding(Company $company): BrandingSetting
    {
        $branding = BrandingSetting::query()->where('company_id', $company->id)->first();

        if (! $branding) {
            $legacyLogo = null;
            try {
                $legacyLogo = SystemSetting::query()->where('key', 'company_logo')->value('value');
            } catch (Throwable) {
                // Ignore if settings table not available
            }

            $branding = BrandingSetting::query()->create([
                'company_id' => $company->id,
                'logo_path' => null,
                'logo_disk' => 'public',
                'app_name' => $company->name,
                'legacy_logo_url' => is_string($legacyLogo) && trim($legacyLogo) !== '' ? trim($legacyLogo) : null,
            ]);
        }

        return $branding;
    }

    public function replaceLogo(Company $company, UploadedFile $logo, ?int $userId = null): BrandingSetting
    {
        $branding = $this->getOrCreateBranding($company);
        $oldPath = $branding->logo_path;

        // 1. Store the new file first under the company's isolated directory
        $stored = $this->fileStorage->storePublic($company, $logo, 'branding_logo');

        try {
            // 2. Transactionally update the branding settings record
            DB::transaction(function () use ($branding, $stored) {
                $branding->update([
                    'logo_path' => $stored->path,
                    'logo_disk' => $stored->disk,
                ]);
            });
        } catch (Throwable $exception) {
            // Roll back the uploaded file if database update fails
            Storage::disk($stored->disk)->delete($stored->path);

            throw $exception;
        }

        // 3. Confirm database update succeeded before safely deleting previous managed file
        if (! empty($oldPath) && $this->fileStorage->belongsToCompany($company, $oldPath)) {
            $this->fileStorage->delete($company, 'public', $oldPath);
        }

        // 4. Synchronize legacy system setting so existing components keep working
        $this->syncLegacySystemSetting($company, $branding->fresh());

        // 5. Audit log
        if ($this->auditLogService) {
            try {
                $this->auditLogService->log(
                    AuditEventType::SETTING_UPDATED,
                    'updated',
                    'branding_setting',
                    $branding->id,
                    [
                        'user_id' => $userId,
                        'metadata' => [
                            'company_id' => $company->id,
                            'storage_namespace' => $company->storage_namespace,
                            'new_logo_path' => $stored->path,
                            'old_logo_path' => $oldPath,
                            'original_name' => $stored->originalName,
                        ],
                        'notes' => 'Company logo replaced with multi-tenant storage.',
                    ]
                );
            } catch (Throwable) {
                // Non-blocking audit log
            }
        }

        return $branding->fresh();
    }

    public function resetLogo(Company $company, ?int $userId = null): BrandingSetting
    {
        $branding = $this->getOrCreateBranding($company);
        $oldPath = $branding->logo_path;

        DB::transaction(function () use ($branding) {
            $branding->update([
                'logo_path' => null,
            ]);
        });

        if (! empty($oldPath) && $this->fileStorage->belongsToCompany($company, $oldPath)) {
            $this->fileStorage->delete($company, 'public', $oldPath);
        }

        $this->syncLegacySystemSetting($company, $branding->fresh());

        return $branding->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicBranding(Company $company): array
    {
        $branding = $this->getOrCreateBranding($company);

        return [
            'app_name' => $branding->app_name ?: $company->name,
            'company_name' => $company->name,
            'logo_url' => $branding->logo_url,
            'logo_path' => $branding->logo_path,
            'tenant_id' => $company->storage_namespace,
        ];
    }

    private function syncLegacySystemSetting(Company $company, BrandingSetting $branding): void
    {
        try {
            $setting = SystemSetting::query()->where('key', 'company_logo')->first();
            if ($setting) {
                $value = $branding->logo_url;
                $setting->update([
                    'value' => $value,
                ]);

                if ($this->systemSettingService) {
                    $this->systemSettingService->clearCache();
                }
            }
        } catch (Throwable) {
            // Ignore if setting does not exist
        }
    }
}
