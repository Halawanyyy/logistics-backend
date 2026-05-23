<?php

namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $shipment = $this->relationLoaded('shipment') ? $this->shipment : null;
        $latestStockReservation = $this->relationLoaded('latestStockReservation') ? $this->latestStockReservation : null;
        $hasStockReservations = $latestStockReservation !== null;

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
            'original_order_id' => $this->original_order_id,
            'original_order_number' => $this->whenLoaded('originalOrder', fn () => $this->originalOrder?->order_number),
            'swap_notes' => $this->swap_notes,
            'payment_type' => $this->payment_type,
            'fulfillment_type' => $this->fulfillment_type,
            'pickup_type' => $this->fulfillment_type,
            'is_fragile' =>(bool) $this->is_fragile,
            'allow_inspection' =>(bool) $this->allow_inspection,
            'requires_packaging' =>(bool) $this->requires_packaging,
            'package_notes' => $this->package_notes,
            'source' => $this->source,
            'external_source' => $this->external_source,
            'external_order_id' => $this->external_order_id,
            'external_order_number' => $this->external_order_number,
            'requires_review' =>(bool) $this->requires_review,
            'review_reason' => $this->review_reason,
            'status' => $this->status,
            'notes' => $this->notes,
            'shipment' => $shipment !== null ? [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'shipment_status' => $shipment->status,
            ] : null,
            'has_stock_reservations' => $hasStockReservations,
            'stock_reservation_status' => $hasStockReservations ? $latestStockReservation->status : null,
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'created_at' => $this->created_at,
        ];
    }
}
