<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Label\Controllers\ResourceLabelController;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalShipmentsRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalShipmentDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalShipmentResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalTrackingResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalShipmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MerchantPortalShipmentController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalShipmentService $merchantPortalShipmentService,
        private readonly ResourceLabelController $resourceLabelController,
    ) {
    }

    public function index(ListMerchantPortalShipmentsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'shipments.view');

        $shipments = $this->merchantPortalShipmentService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalShipmentResource::collection($shipments);
    }

    public function show(Request $request, int $shipment): MerchantPortalShipmentDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'shipments.view');

        return new MerchantPortalShipmentDetailResource(
            $this->merchantPortalShipmentService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $shipment,
            )
        );
    }

    public function tracking(Request $request, int $shipment): MerchantPortalTrackingResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'shipments.view');

        return new MerchantPortalTrackingResource(
            $this->merchantPortalShipmentService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $shipment,
            )
        );
    }

    public function label(RenderLabelRequest $request, int $shipment): Response
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'labels.print');

        $ownedShipment = $this->merchantPortalShipmentService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $shipment,
        );

        return $this->resourceLabelController->shipmentLabel($request, $ownedShipment);
    }

    public function waybill(RenderLabelRequest $request, int $shipment): Response
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'labels.print');

        $ownedShipment = $this->merchantPortalShipmentService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $shipment,
        );

        return $this->resourceLabelController->shipmentWaybill($request, $ownedShipment);
    }
}
