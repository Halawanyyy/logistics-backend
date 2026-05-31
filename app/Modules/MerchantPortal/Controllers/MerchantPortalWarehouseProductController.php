<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Label\Controllers\ResourceLabelController;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\MerchantPortal\Requests\BulkMerchantPortalWarehouseProductLabelsRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalWarehouseProductsRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalWarehouseProductDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalWarehouseProductResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MerchantPortalWarehouseProductController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalWarehouseService $merchantPortalWarehouseService,
        private readonly ResourceLabelController $resourceLabelController,
    ) {
    }

    public function index(ListMerchantPortalWarehouseProductsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        $products = $this->merchantPortalWarehouseService
            ->productQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalWarehouseProductResource::collection($products);
    }

    public function show(Request $request, int $warehouseProduct): MerchantPortalWarehouseProductDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.view');

        return new MerchantPortalWarehouseProductDetailResource(
            $this->merchantPortalWarehouseService->findOwnedProduct(
                $this->merchantPortalAccessService->merchantId($request),
                $warehouseProduct,
            )
        );
    }

    public function label(RenderLabelRequest $request, int $warehouseProduct): Response
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'labels.print');

        $product = $this->merchantPortalWarehouseService->findOwnedProduct(
            $this->merchantPortalAccessService->merchantId($request),
            $warehouseProduct,
        );

        return $this->resourceLabelController->warehouseProductLabel($request, $product);
    }

    public function bulkLabels(BulkMerchantPortalWarehouseProductLabelsRequest $request): Response
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'labels.print');

        return $this->merchantPortalWarehouseService->renderOwnedProductLabels(
            $request->validated()['items'],
            $request->validated(),
            $this->merchantPortalAccessService->merchantId($request),
        );
    }
}
