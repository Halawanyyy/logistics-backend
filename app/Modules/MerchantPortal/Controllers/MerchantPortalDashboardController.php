<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceSummaryRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalDashboardSummaryResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalDashboardService;

class MerchantPortalDashboardController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalDashboardService $merchantPortalDashboardService,
    ) {
    }

    public function summary(MerchantPortalFinanceSummaryRequest $request): MerchantPortalDashboardSummaryResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $requiredCapability = $this->merchantPortalAccessService->can($merchantUser, 'dashboard.view')
            ? 'dashboard.view'
            : 'profile.view';
        $this->merchantPortalAccessService->assertCapability($merchantUser, $requiredCapability);

        return new MerchantPortalDashboardSummaryResource(
            $this->merchantPortalDashboardService->summary(
                $this->merchantPortalAccessService->currentMerchant($request),
                $merchantUser,
                $request->validated(),
            )
        );
    }
}
