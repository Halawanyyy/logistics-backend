<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $shipment = $this->relationLoaded('shipment') ? $this->shipment : null;
        $waybill = $this->relationLoaded('waybill') ? $this->waybill : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'waybill_number' => $waybill?->waybill_number,
            'status' => $this->status,
            'status_label' => $labels->label('orders.status', $this->status),
            'fulfillment_type' => $this->fulfillment_type,
            'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->fulfillment_type),
            'payment_type' => $this->payment_type,
            'payment_type_label' => $labels->label('orders.payment_type', $this->payment_type),
            'order_type' => $this->order_type,
            'order_type_label' => $labels->label('orders.type', $this->order_type),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_location_summary' => [
                'governorate_id' => $this->delivery_governorate_id,
                'governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'area_id' => $this->delivery_area_id,
                'area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'address' => $this->delivery_address,
            ],
            'cod_amount' => $this->cod_amount,
            'shipping_fee' => $this->shipping_fee,
            'requires_packaging' => (bool) $this->requires_packaging,
            'total_packaging_fee' => $this->total_packaging_fee,
            'total_amount' => number_format((float) $this->cod_amount + (float) $this->shipping_fee, 2, '.', ''),
            'has_shipment' => $shipment !== null,
            'shipment_summary' => $shipment !== null ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status,
                'status_label' => $labels->label('shipments.status', $shipment->status),
            ] : null,
            'warehouse_fulfillment_status' => $this->warehouse_fulfillment_status,
            'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->warehouse_fulfillment_status),
            'created_at' => $this->created_at,
        ];
    }
}
