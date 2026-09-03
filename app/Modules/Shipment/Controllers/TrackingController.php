<?php

namespace App\Modules\Shipment\Controllers;

use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Resources\PublicShipmentTrackingResource;

class TrackingController
{
    public function show(string $shipmentNumber): PublicShipmentTrackingResource
    {
        $shipment = Shipment::query()
            ->with(['deliveryGovernorate', 'deliveryArea', 'histories', 'waybill'])
            ->where(function ($query) use ($shipmentNumber): void {
                $query->where('shipment_number', $shipmentNumber)
                    ->orWhereHas('waybill', function ($waybillQuery) use ($shipmentNumber): void {
                        $waybillQuery->where('waybill_number', $shipmentNumber)
                            ->orWhere('barcode', $shipmentNumber);
                    });
            })
            ->first();

        if ($shipment === null) {
            abort(response()->json([
                'message' => __('shipments.not_found'),
            ], 404));
        }

        return new PublicShipmentTrackingResource($shipment);
    }
}
