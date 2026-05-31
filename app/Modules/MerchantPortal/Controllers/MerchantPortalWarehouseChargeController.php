<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalWarehouseChargesRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalWarehouseChargeResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalWarehouseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalWarehouseChargeController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalWarehouseService $merchantPortalWarehouseService,
    ) {
    }

    public function index(ListMerchantPortalWarehouseChargesRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        $charges = $this->merchantPortalWarehouseService
            ->chargeQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalWarehouseChargeResource::collection($charges);
    }
}
