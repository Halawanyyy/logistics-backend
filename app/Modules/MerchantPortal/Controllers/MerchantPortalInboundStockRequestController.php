<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalInboundRequestsRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalInboundRequestRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalInboundStockRequestDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalInboundStockRequestResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalInboundStockRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalInboundStockRequestController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalInboundStockRequestService $merchantPortalInboundStockRequestService,
    ) {
    }

    public function index(ListMerchantPortalInboundRequestsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.inbound_requests.view');

        $requests = $this->merchantPortalInboundStockRequestService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalInboundStockRequestResource::collection($requests);
    }

    public function store(StoreMerchantPortalInboundRequestRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.inbound_requests.create');

        $inboundRequest = $this->merchantPortalInboundStockRequestService->createForMerchant(
            $request->validated(),
            $this->merchantPortalAccessService->merchantId($request),
            (int) $request->user()->id,
        );

        return (new MerchantPortalInboundStockRequestDetailResource($inboundRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $inboundRequest): MerchantPortalInboundStockRequestDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.inbound_requests.view');

        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $inboundRequest,
            )
        );
    }

    public function cancel(Request $request, int $inboundRequest): MerchantPortalInboundStockRequestDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'warehouse.inbound_requests.cancel');

        $ownedRequest = $this->merchantPortalInboundStockRequestService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $inboundRequest,
        );

        return new MerchantPortalInboundStockRequestDetailResource(
            $this->merchantPortalInboundStockRequestService->cancelForMerchant($ownedRequest)
        );
    }
}
