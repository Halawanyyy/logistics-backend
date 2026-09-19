<?php

namespace App\Modules\Branding\Support;

use App\Models\Company;

final class CompanyStoragePath
{
    public function brandingLogo(Company $company): string
    {
        return sprintf(
            'companies/%s/branding/logos',
            $company->storage_namespace
        );
    }

    public function attachment(
        Company $company,
        string $module,
        string $recordIdentifier
    ): string {
        return sprintf(
            'companies/%s/attachments/%s/%s/%s/%s',
            $company->storage_namespace,
            $module,
            $recordIdentifier,
            now()->format('Y'),
            now()->format('m')
        );
    }
}
