<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalInventoryRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalInventoryStockResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalInventoryController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalWarehouseService $merchantPortalWarehouseService,
    ) {
    }

    public function index(ListMerchantPortalInventoryRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        $stocks = $this->merchantPortalWarehouseService
            ->inventoryQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalInventoryStockResource::collection($stocks);
    }

    public function lowStock(ListMerchantPortalInventoryRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        $stocks = $this->merchantPortalWarehouseService
            ->inventoryQueryForMerchant(
                $this->merchantPortalAccessService->merchantId($request),
                array_merge($request->validated(), ['low_stock' => true]),
            )
            ->get();

        return MerchantPortalInventoryStockResource::collection($stocks);
    }

    public function show(Request $request, int $inventoryStock): MerchantPortalInventoryStockResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        return new MerchantPortalInventoryStockResource(
            $this->merchantPortalWarehouseService->findOwnedInventoryStock(
                $this->merchantPortalAccessService->merchantId($request),
                $inventoryStock,
            )
        );
    }
}
