<?php

namespace App\Modules\Branding\Support;

use App\Models\Company;
use Illuminate\Support\Str;

final class CompanyStorageNamespace
{
    public static function generate(Company $company): string
    {
        $identifier = $company->uuid ?? $company->getKey();
        $slug = Str::slug($company->name);

        return sprintf(
            'company-%s--%s',
            $identifier,
            $slug !== '' ? $slug : 'tenant'
        );
    }
}
