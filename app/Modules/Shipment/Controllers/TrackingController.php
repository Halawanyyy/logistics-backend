<?php

namespace App\Modules\Shipment\Controllers;

use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Resources\PublicShipmentTrackingResource;

class TrackingController
{
    public function show(string $shipmentNumber): PublicShipmentTrackingResource
    {
        $shipment = Shipment::query()
            ->with(['deliveryGovernorate', 'deliveryArea', 'histories'])
            ->where('shipment_number', $shipmentNumber)
            ->first();

        if ($shipment === null) {
            abort(response()->json([
                'message' => __('shipments.not_found'),
            ], 404));
        }

        return new PublicShipmentTrackingResource($shipment);
    }
}
