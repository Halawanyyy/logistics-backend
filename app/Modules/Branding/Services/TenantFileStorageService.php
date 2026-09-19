<?php

namespace App\Modules\Branding\Services;

use App\Models\Company;
use App\Modules\Branding\Contracts\CompanyFileStorage;
use App\Modules\Branding\Support\CompanyStoragePath;
use App\Modules\Branding\Support\StoredCompanyFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantFileStorageService implements CompanyFileStorage
{
    public function __construct(
        private readonly CompanyStoragePath $pathBuilder = new CompanyStoragePath(),
    ) {
    }

    public function storePublic(
        Company $company,
        UploadedFile $file,
        string $category
    ): StoredCompanyFile {
        $directory = $this->resolveDirectory($company, $category);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $fileName = Str::uuid()->toString().'.'.$extension;

        $path = $file->storeAs($directory, $fileName, 'public');

        return new StoredCompanyFile(
            companyId: $company->id,
            disk: 'public',
            path: $path,
            url: Storage::disk('public')->url($path),
            originalName: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            size: (int) $file->getSize(),
            storageNamespace: $company->storage_namespace,
        );
    }

    public function storePrivate(
        Company $company,
        UploadedFile $file,
        string $category,
        ?string $recordIdentifier = null
    ): StoredCompanyFile {
        $disk = 'local';
        $directory = $category === 'attachment' && $recordIdentifier !== null
            ? $this->pathBuilder->attachment($company, 'general', $recordIdentifier)
            : sprintf('companies/%s/private/%s', $company->storage_namespace, $category);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $fileName = Str::uuid()->toString().'.'.$extension;

        $path = $file->storeAs($directory, $fileName, $disk);

        return new StoredCompanyFile(
            companyId: $company->id,
            disk: $disk,
            path: $path,
            url: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            size: (int) $file->getSize(),
            storageNamespace: $company->storage_namespace,
        );
    }

    public function replacePublic(
        Company $company,
        UploadedFile $file,
        string $category,
        ?string $oldPath
    ): StoredCompanyFile {
        $stored = $this->storePublic($company, $file, $category);

        if ($oldPath && $this->belongsToCompany($company, $oldPath)) {
            $this->delete($company, 'public', $oldPath);
        }

        return $stored;
    }

    public function delete(
        Company $company,
        string $disk,
        string $path
    ): void {
        if (! $this->belongsToCompany($company, $path)) {
            return;
        }

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function belongsToCompany(
        Company $company,
        string $path
    ): bool {
        if (empty($company->storage_namespace) || empty($path)) {
            return false;
        }

        $expectedPrefix = sprintf('companies/%s/', $company->storage_namespace);

        return Str::startsWith(str_replace('\\', '/', $path), $expectedPrefix);
    }

    private function resolveDirectory(Company $company, string $category): string
    {
        return match ($category) {
            'branding_logo', 'logo', 'logos' => $this->pathBuilder->brandingLogo($company),
            'branding_cover', 'covers' => sprintf('companies/%s/branding/covers', $company->storage_namespace),
            'branding_icon', 'icons' => sprintf('companies/%s/branding/icons', $company->storage_namespace),
            'media_products' => sprintf('companies/%s/media/products', $company->storage_namespace),
            'media_merchants' => sprintf('companies/%s/media/merchants', $company->storage_namespace),
            'temporary' => sprintf('companies/%s/temporary', $company->storage_namespace),
            default => sprintf('companies/%s/branding/logos', $company->storage_namespace),
        };
    }
}
