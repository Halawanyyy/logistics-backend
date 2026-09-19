<?php

namespace App\Modules\Branding\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Branding\Contracts\CurrentCompanyResolver;
use App\Modules\Branding\Support\CompanyStorageNamespace;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DefaultCompanyResolver implements CurrentCompanyResolver
{
    public function resolve(Request $request): Company
    {
        // 1. If explicit authorized company in route parameter
        $routeCompany = $request->route('company');
        if ($routeCompany instanceof Company) {
            return $routeCompany;
        }

        if (is_numeric($routeCompany)) {
            $found = Company::find($routeCompany);
            if ($found) {
                return $found;
            }
        }

        // 2. If authenticated user belongs to a company
        $user = $request->user();
        if ($user instanceof User && $user->company_id) {
            $userCompany = $user->company()->first();
            if ($userCompany instanceof Company) {
                return $userCompany;
            }
        }

        // 3. If tenant header supplied
        $tenantHeader = $request->header('X-Tenant-ID') ?: $request->header('X-Tenant');
        if (! empty($tenantHeader) && is_string($tenantHeader) && $tenantHeader !== 'main') {
            $companyByTenant = Company::query()
                ->where('storage_namespace', $tenantHeader)
                ->orWhere('slug', $tenantHeader)
                ->first();

            if ($companyByTenant) {
                return $companyByTenant;
            }
        }

        // 4. Default company
        $defaultCompany = Company::query()->first();
        if ($defaultCompany) {
            return $defaultCompany;
        }

        // Lazy seed if running before migrations in special test environments
        $name = 'Fast Logistics';
        try {
            $nameSetting = SystemSetting::query()->where('key', 'company_name')->value('value');
            if (! empty($nameSetting)) {
                $name = (string) $nameSetting;
            }
        } catch (\Throwable) {
            // Ignore if table missing
        }

        $slug = Str::slug($name) ?: 'fast-logistics';
        $created = new Company();
        $created->name = $name;
        $created->slug = $slug;
        $created->storage_namespace = 'temp';
        $created->save();

        $created->storage_namespace = CompanyStorageNamespace::generate($created);
        $created->saveQuietly();

        return $created;
    }
}
