<?php

namespace App\Modules\Driver\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverManifestPrintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => $this->items);

        return [
            'manifest' => [
                'manifest_number' => $this->manifest_number,
                'date' => $this->manifest_date?->toDateString(),
                'driver_name' => $this->whenLoaded('driver', fn () => $this->driver?->name),
                'driver_phone' => $this->whenLoaded('driver', fn () => $this->driver?->phone),
                'total_shipments' => $this->total_shipments,
                'expected_cod_amount' => $this->expected_cod_amount,
                'status' => $this->status,
                'notes' => $this->notes,
            ],
            'shipments' => $this->whenLoaded('items', fn () => $this->items->map(function ($item): array {
                return [
                    'shipment_number' => $item->shipment_number,
                    'order_number' => $item->shipment?->order?->order_number,
                    'customer_name' => $item->customer_name,
                    'customer_phone' => $item->customer_phone,
                    'delivery_address' => $item->delivery_address,
                    'cod_amount' => $item->cod_amount,
                    'shipping_fee' => $item->shipping_fee,
                    'merchant_name' => $item->shipment?->merchant?->name,
                    'notes' => $item->notes,
                ];
            })->values()),
            'summary' => [
                'total_cod' => $this->expected_cod_amount,
                'total_shipments' => $this->total_shipments,
                'delivered_shipments' => $this->whenLoaded('items', fn () => $this->items->whereIn('final_status', ['delivered', 'partially_delivered'])->count()),
                'failed_shipments' => $this->whenLoaded('items', fn () => $this->items->where('final_status', 'failed')->count()),
            ],
        ];
    }
}
