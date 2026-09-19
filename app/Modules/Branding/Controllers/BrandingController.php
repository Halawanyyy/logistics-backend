<?php

namespace App\Modules\Branding\Controllers;

use App\Modules\Branding\Contracts\CurrentCompanyResolver;
use App\Modules\Branding\Requests\UploadLogoRequest;
use App\Modules\Branding\Services\BrandingService;
use App\Modules\Branding\Services\DefaultCompanyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController
{
    public function __construct(
        private readonly BrandingService $brandingService,
        private readonly CurrentCompanyResolver $companyResolver = new DefaultCompanyResolver(),
    ) {
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $company = $this->companyResolver->resolve($request);

        // Security: Never trust client-supplied company_id if user belongs to a specific company
        $user = $request->user();
        if ($user && $user->company_id && $user->company_id !== $company->id) {
            return response()->json([
                'message' => 'Unauthorized cross-company access.',
            ], 403);
        }

        $branding = $this->brandingService->replaceLogo(
            $company,
            $request->file('logo'),
            $user?->id
        );

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'data' => [
                'company_id' => $company->id,
                'app_name' => $branding->app_name ?: $company->name,
                'company_name' => $company->name,
                'logo_url' => $branding->logo_url,
                'logo_path' => $branding->logo_path,
                'tenant_id' => $company->storage_namespace,
            ],
        ]);
    }

    public function publicBranding(Request $request): JsonResponse
    {
        $company = $this->companyResolver->resolve($request);
        $brandingData = $this->brandingService->getPublicBranding($company);

        return response()->json([
            'data' => $brandingData,
        ]);
    }

    public function resetLogo(Request $request): JsonResponse
    {
        $company = $this->companyResolver->resolve($request);
        $user = $request->user();

        if ($user && $user->company_id && $user->company_id !== $company->id) {
            return response()->json([
                'message' => 'Unauthorized cross-company access.',
            ], 403);
        }

        $branding = $this->brandingService->resetLogo($company, $user?->id);

        return response()->json([
            'message' => 'Logo reset successfully.',
            'data' => [
                'company_id' => $company->id,
                'app_name' => $branding->app_name ?: $company->name,
                'company_name' => $company->name,
                'logo_url' => $branding->logo_url,
                'logo_path' => $branding->logo_path,
                'tenant_id' => $company->storage_namespace,
            ],
        ]);
    }
}
