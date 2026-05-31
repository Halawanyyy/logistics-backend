<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalStockMovementsRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalStockMovementResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalWarehouseService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalStockMovementController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalWarehouseService $merchantPortalWarehouseService,
    ) {
    }

    public function index(ListMerchantPortalStockMovementsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        $movements = $this->merchantPortalWarehouseService
            ->movementQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalStockMovementResource::collection($movements);
    }
}
