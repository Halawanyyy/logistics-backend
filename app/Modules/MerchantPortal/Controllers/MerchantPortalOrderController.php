<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Label\Controllers\ResourceLabelController;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalOrdersRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalOrderRequest;
use App\Modules\MerchantPortal\Requests\UpdateMerchantPortalOrderRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalOrderDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalOrderResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MerchantPortalOrderController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalOrderService $merchantPortalOrderService,
        private readonly ResourceLabelController $resourceLabelController,
    ) {
    }

    public function index(ListMerchantPortalOrdersRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.view');

        $orders = $this->merchantPortalOrderService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalOrderResource::collection($orders);
    }

    public function store(StoreMerchantPortalOrderRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.create');

        $order = $this->merchantPortalOrderService->createForMerchant(
            $request->validated(),
            $this->merchantPortalAccessService->merchantId($request),
            $request->user()?->id,
        );

        return (new MerchantPortalOrderDetailResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $order): MerchantPortalOrderDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.view');

        return new MerchantPortalOrderDetailResource(
            $this->merchantPortalOrderService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $order,
            )
        );
    }

    public function update(UpdateMerchantPortalOrderRequest $request, int $order): MerchantPortalOrderDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.update');

        $ownedOrder = $this->merchantPortalOrderService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $order,
        );

        return new MerchantPortalOrderDetailResource(
            $this->merchantPortalOrderService->update($ownedOrder, $request->validated())
        );
    }

    public function confirm(Request $request, int $order): MerchantPortalOrderDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.update');

        $ownedOrder = $this->merchantPortalOrderService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $order,
        );

        return new MerchantPortalOrderDetailResource(
            $this->merchantPortalOrderService->confirm($ownedOrder)
        );
    }

    public function cancel(Request $request, int $order): MerchantPortalOrderDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'orders.update');

        $ownedOrder = $this->merchantPortalOrderService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $order,
        );

        return new MerchantPortalOrderDetailResource(
            $this->merchantPortalOrderService->cancel($ownedOrder, $request->user()?->id)
        );
    }

    public function label(RenderLabelRequest $request, int $order): Response
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'labels.print');

        $ownedOrder = $this->merchantPortalOrderService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $order,
        );

        return $this->resourceLabelController->orderLabel($request, $ownedOrder);
    }
}
