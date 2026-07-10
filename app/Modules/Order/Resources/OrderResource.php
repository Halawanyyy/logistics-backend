<?php

namespace App\Modules\Order\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $shipment = $this->relationLoaded('shipment') ? $this->shipment : null;
        $latestStockReservation = $this->relationLoaded('latestStockReservation') ? $this->latestStockReservation : null;
        $hasStockReservations = $latestStockReservation !== null;
        $shipmentSummary = $shipment !== null ? [
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'shipment_status' => $shipment->status,
            'shipment_status_label' => $labels->label('shipments.status', $shipment->status),
        ] : null;

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'order_number' => $this->order_number,
            'barcode' => $this->barcode,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_phone_alt' => $this->customer_phone_alt,
            'delivery_governorate_id' => $this->delivery_governorate_id,
            'delivery_governorate_name' => $this->whenLoaded('deliveryGovernorate', fn () => $this->deliveryGovernorate?->name),
            'delivery_area_id' => $this->delivery_area_id,
            'delivery_area_name' => $this->whenLoaded('deliveryArea', fn () => $this->deliveryArea?->name),
            'delivery_address' => $this->delivery_address,
            'delivery_notes' => $this->delivery_notes,
            'pickup_governorate_id' => $this->pickup_governorate_id,
            'pickup_area_id' => $this->pickup_area_id,
            'pickup_address' => $this->pickup_address,
            'pickup_notes' => $this->pickup_notes,
            'cod_amount' => $this->cod_amount,
            'shipping_fee' => $this->shipping_fee,
            'delivered_cod_amount' => $this->delivered_cod_amount,
            'order_type' => $this->order_type,
            'order_type_label' => $labels->label('orders.type', $this->order_type),
            'original_order_id' => $this->original_order_id,
            'original_order_number' => $this->whenLoaded('originalOrder', fn () => $this->originalOrder?->order_number),
            'swap_notes' => $this->swap_notes,
            'payment_type' => $this->payment_type,
            'payment_type_label' => $labels->label('orders.payment_type', $this->payment_type),
            'fulfillment_type' => $this->fulfillment_type,
            'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->fulfillment_type),
            'pickup_type' => $this->fulfillment_type,
            'warehouse_fulfillment_status' => $this->warehouse_fulfillment_status,
            'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->warehouse_fulfillment_status),
            'is_fragile' => (bool) $this->is_fragile,
            'allow_inspection' => (bool) $this->allow_inspection,
            'requires_packaging' => (bool) $this->requires_packaging,
            'package_notes' => $this->package_notes,
            'total_packaging_fee' => $this->total_packaging_fee,
            'source' => $this->source,
            'source_label' => $labels->label('orders.source', $this->source),
            'external_source' => $this->external_source,
            'external_order_id' => $this->external_order_id,
            'external_order_number' => $this->external_order_number,
            'requires_review' => (bool) $this->requires_review,
            'review_reason' => $this->review_reason,
            'status' => $this->status,
            'status_label' => $labels->label('orders.status', $this->status),
            'notes' => $this->notes,
            'merchant_summary' => [
                'id' => $this->merchant_id,
                'name' => $this->relationLoaded('merchant') ? $this->merchant?->name : null,
            ],
            'customer' => [
                'name' => $this->customer_name,
                'phone' => $this->customer_phone,
                'alternate_phone' => $this->customer_phone_alt,
            ],
            'location_summary' => [
                'delivery_governorate_id' => $this->delivery_governorate_id,
                'delivery_governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'delivery_area_id' => $this->delivery_area_id,
                'delivery_area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'delivery_address' => $this->delivery_address,
            ],
            'payment_summary' => [
                'payment_type' => $this->payment_type,
                'payment_type_label' => $labels->label('orders.payment_type', $this->payment_type),
                'cod_amount' => $this->cod_amount,
                'shipping_fee' => $this->shipping_fee,
                'total_packaging_fee' => $this->total_packaging_fee,
                'delivered_cod_amount' => $this->delivered_cod_amount,
            ],
            'warehouse_summary' => [
                'fulfillment_type' => $this->fulfillment_type,
                'fulfillment_type_label' => $labels->label('orders.fulfillment_type', $this->fulfillment_type),
                'warehouse_fulfillment_status' => $this->warehouse_fulfillment_status,
                'warehouse_fulfillment_status_label' => $labels->label('orders.warehouse_fulfillment_status', $this->warehouse_fulfillment_status),
                'has_stock_reservations' => $hasStockReservations,
                'stock_reservation_status' => $hasStockReservations ? $latestStockReservation->status : null,
            ],
            'shipment' => $shipmentSummary,
            'shipment_summary' => $shipmentSummary,
            'has_stock_reservations' => $hasStockReservations,
            'stock_reservation_status' => $hasStockReservations ? $latestStockReservation->status : null,
            'stock_reservation_summary' => [
                'has_stock_reservations' => $hasStockReservations,
                'latest_status' => $hasStockReservations ? $latestStockReservation->status : null,
                'count' => $this->relationLoaded('stockReservations') ? $this->stockReservations->count() : null,
            ],
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'packaging_items' => $this->whenLoaded('packagingItems', fn () => OrderPackagingItemResource::collection($this->packagingItems)),
            'allowed_actions' => [
                'can_confirm' => in_array($this->status, ['draft', 'pending_review'], true) && ! (bool) $this->requires_review,
                'can_edit' => $shipment === null
                    && ! in_array($this->status, ['shipment_created', 'partially_delivered', 'delivered', 'failed', 'cancelled'], true)
                    && ! ($this->fulfillment_type === 'from_warehouse' && ! in_array($this->warehouse_fulfillment_status, ['not_required', 'pending_reservation'], true)),
                'can_delete' => $shipment === null
                    && ! in_array($this->status, ['shipment_created', 'partially_delivered', 'delivered', 'failed', 'cancelled'], true)
                    && ! ($this->fulfillment_type === 'from_warehouse' && ! in_array($this->warehouse_fulfillment_status, ['not_required', 'pending_reservation'], true)),
                'can_create_shipment' => $shipment === null
                    && $this->status === 'confirmed'
                    && (
                        $this->fulfillment_type !== 'from_warehouse'
                        || $this->warehouse_fulfillment_status === 'fulfilled'
                    ),
                'can_reserve_stock' => $this->fulfillment_type === 'from_warehouse' && in_array($this->warehouse_fulfillment_status, ['pending_reservation'], true),
                'can_release_stock' => $this->fulfillment_type === 'from_warehouse' && in_array($this->warehouse_fulfillment_status, ['reserved'], true),
                'can_fulfill_from_warehouse' => $this->fulfillment_type === 'from_warehouse' && in_array($this->warehouse_fulfillment_status, ['reserved', 'picking', 'picked', 'packing', 'packed', 'ready_for_dispatch'], true),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
