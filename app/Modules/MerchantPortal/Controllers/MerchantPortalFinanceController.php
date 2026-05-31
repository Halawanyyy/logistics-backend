<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceSummaryRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalFinanceSummaryResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalFinanceService;

class MerchantPortalFinanceController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
    ) {
    }

    public function summary(MerchantPortalFinanceSummaryRequest $request): MerchantPortalFinanceSummaryResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'finance.view');

        return new MerchantPortalFinanceSummaryResource(
            $this->merchantPortalFinanceService->financeSummary(
                $this->merchantPortalAccessService->currentMerchant($request),
                $request->validated(),
            )
        );
    }
}
