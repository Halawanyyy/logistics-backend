<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\LinkMerchantInboundStockRequestReceiptRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantInboundStockRequestsAdminRequest;
use App\Modules\MerchantPortal\Requests\ReviewMerchantInboundStockRequestRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalInboundStockRequestDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalInboundStockRequestResource;
use App\Modules\MerchantPortal\Services\MerchantPortalInboundStockRequestService;
use App\Modules\Warehouse\Models\WarehouseReceipt;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantInboundStockRequestAdminController
{
    public function __construct(
        private readonly MerchantPortalInboundStockRequestService $merchantPortalInboundStockRequestService,
    ) {
    }

    public function index(ListMerchantInboundStockRequestsAdminRequest $request): AnonymousResourceCollection
    {
        $requests = $this->merchantPortalInboundStockRequestService
            ->adminQuery($request->validated())
            ->get();

        return MerchantPortalInboundStockRequestResource::collection($requests);
    }

    public function show(int $inboundRequest): MerchantPortalInboundStockRequestDetailResource
    {
        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->findAdmin($inboundRequest)
        );
    }

    public function approve(ReviewMerchantInboundStockRequestRequest $request, int $inboundRequest): MerchantPortalInboundStockRequestDetailResource
    {
        $validated = $request->validated();

        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->approve(
                $this->merchantPortalInboundStockRequestService->findAdmin($inboundRequest),
                $request->user()?->id,
                $validated['internal_notes'] ?? null,
            )
        );
    }

    public function reject(ReviewMerchantInboundStockRequestRequest $request, int $inboundRequest): MerchantPortalInboundStockRequestDetailResource
    {
        $validated = $request->validated();

        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->reject(
                $this->merchantPortalInboundStockRequestService->findAdmin($inboundRequest),
                $request->user()?->id,
                $validated['internal_notes'] ?? null,
            )
        );
    }

    public function linkWarehouseReceipt(
        LinkMerchantInboundStockRequestReceiptRequest $request,
        int $inboundRequest
    ): MerchantPortalInboundStockRequestDetailResource {
        $validated = $request->validated();
        $warehouseReceipt = WarehouseReceipt::query()->findOrFail((int) $validated['warehouse_receipt_id']);

        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->linkWarehouseReceipt(
                $this->merchantPortalInboundStockRequestService->findAdmin($inboundRequest),
                $warehouseReceipt,
                $request->user()?->id,
                $validated['internal_notes'] ?? null,
            )
        );
    }
}
