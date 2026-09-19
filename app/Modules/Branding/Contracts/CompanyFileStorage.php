<?php

namespace App\Modules\Branding\Contracts;

use App\Models\Company;
use App\Modules\Branding\Support\StoredCompanyFile;
use Illuminate\Http\UploadedFile;

interface CompanyFileStorage
{
    public function storePublic(
        Company $company,
        UploadedFile $file,
        string $category
    ): StoredCompanyFile;

    public function storePrivate(
        Company $company,
        UploadedFile $file,
        string $category,
        ?string $recordIdentifier = null
    ): StoredCompanyFile;

    public function replacePublic(
        Company $company,
        UploadedFile $file,
        string $category,
        ?string $oldPath
    ): StoredCompanyFile;

    public function delete(
        Company $company,
        string $disk,
        string $path
    ): void;

    public function belongsToCompany(
        Company $company,
        string $path
    ): bool;
}
