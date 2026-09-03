<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Resources\OrderItemResource;
use App\Modules\Order\Resources\OrderPackagingItemResource;
use App\Modules\Warehouse\Enums\WarehouseFulfillmentStatus;
use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalOrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $shipment = $this->relationLoaded('shipment') ? $this->shipment : null;
        $waybill = $this->relationLoaded('waybill') ? $this->waybill : null;
        $latestStockReservation = $this->relationLoaded('latestStockReservation') ? $this->latestStockReservation : null;
        $canModify = $shipment === null
            && ! in_array((string) $this->status, [
                OrderStatus::SHIPMENT_CREATED,
                OrderStatus::PARTIALLY_DELIVERED,
                OrderStatus::DELIVERED,
                OrderStatus::FAILED,
                OrderStatus::CANCELLED,
            ], true)
            && ! (
                $this->fulfillment_type === 'from_warehouse'
                && ! in_array((string) $this->warehouse_fulfillment_status, [
                    WarehouseFulfillmentStatus::NOT_REQUIRED,
                    WarehouseFulfillmentStatus::PENDING_RESERVATION,
                ], true)
            );
        $canCancel = $shipment === null
            && ! in_array((string) $this->status, [
                OrderStatus::SHIPMENT_CREATED,
                OrderStatus::PARTIALLY_DELIVERED,
                OrderStatus::DELIVERED,
                OrderStatus::FAILED,
                OrderStatus::CANCELLED,
            ], true)
            && ! (
                $this->fulfillment_type === 'from_warehouse'
                && in_array((string) $this->warehouse_fulfillment_status, [
                    WarehouseFulfillmentStatus::PICKING,
                    WarehouseFulfillmentStatus::PICKED,
                    WarehouseFulfillmentStatus::PACKING,
                    WarehouseFulfillmentStatus::PACKED,
                    WarehouseFulfillmentStatus::READY_FOR_DISPATCH,
                    WarehouseFulfillmentStatus::FULFILLED,
                    WarehouseFulfillmentStatus::CANCELLED,
                ], true)
            );

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'barcode' => $this->barcode,
            'waybill' => $waybill === null ? null : [
                'id' => $waybill->id,
                'waybill_number' => $waybill->waybill_number,
                'barcode' => $waybill->barcode,
                'status' => $waybill->status,
                'source' => $waybill->source,
            ],
            'status' => $this->status,
            'status_label' => $labels->label('orders.status', $this->status),
            'order_type' => $this->order_type,
            'order_type_label' => $labels->label('orders.type', $this->order_type),
            'fulfillment_type' => $this->fulfillment_type,
            'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->fulfillment_type),
            'warehouse_fulfillment_status' => $this->warehouse_fulfillment_status,
            'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->warehouse_fulfillment_status),
            'is_fragile' => (bool) $this->is_fragile,
            'allow_inspection' => (bool) $this->allow_inspection,
            'payment_type' => $this->payment_type,
            'payment_type_label' => $labels->label('orders.payment_type', $this->payment_type),
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'alternate_phone' => $this->customer_phone_alt,
            ],
            'delivery' => [
                'governorate_id' => $this->delivery_governorate_id,
                'governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'area_id' => $this->delivery_area_id,
                'area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'address' => $this->delivery_address,
                'notes' => $this->delivery_notes,
            ],
            'pickup' => [
                'governorate_id' => $this->pickup_governorate_id,
                'area_id' => $this->pickup_area_id,
                'address' => $this->pickup_address,
                'notes' => $this->pickup_notes,
            ],
            'payment_summary' => [
                'payment_type' => $this->payment_type,
                'payment_type_label' => $labels->label('orders.payment_type', $this->payment_type),
                'cod_amount' => $this->cod_amount,
                'shipping_fee' => $this->shipping_fee,
                'total_packaging_fee' => $this->total_packaging_fee,
                'total_amount' => number_format((float) $this->cod_amount + (float) $this->shipping_fee, 2, '.', ''),
            ],
            'requires_packaging' => (bool) $this->requires_packaging,
            'package_notes' => $this->package_notes,
            'warehouse_summary' => [
                'fulfillment_type' => $this->fulfillment_type,
                'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->fulfillment_type),
                'warehouse_fulfillment_status' => $this->warehouse_fulfillment_status,
                'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->warehouse_fulfillment_status),
                'has_stock_reservations' => $latestStockReservation !== null,
                'latest_stock_reservation_status' => $latestStockReservation?->status,
            ],
            'stock_reservation_summary' => [
                'has_stock_reservations' => $latestStockReservation !== null,
                'latest_status' => $latestStockReservation?->status,
            ],
            'shipment_summary' => $shipment !== null ? [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status,
                'status_label' => $labels->label('shipments.status', $shipment->status),
            ] : null,
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'packaging_items' => $this->whenLoaded('packagingItems', fn () => OrderPackagingItemResource::collection($this->packagingItems)),
            'allowed_actions' => [
                'can_confirm' => in_array((string) $this->status, [OrderStatus::DRAFT, OrderStatus::PENDING_REVIEW], true) && ! (bool) $this->requires_review,
                'can_update' => $canModify,
                'can_cancel' => $canCancel,
                'can_print_label' => true,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
