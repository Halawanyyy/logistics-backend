<?php

namespace App\Modules\Warehouse\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'merchant_id' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant_id),
            'merchant_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant?->name),
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->order_number),
            'shipment_id' => $this->shipment_id,
            'shipment_number' => $this->whenLoaded('shipment', fn () => $this->shipment?->shipment_number),
            'type' => $this->type,
            'movement_type' => $this->type,
            'movement_type_label' => $labels->label('warehouse.stock_movement_type', $this->type),
            'quantity' => $this->quantity,
            'before_available' => $this->before_available,
            'after_available' => $this->after_available,
            'before_reserved' => $this->before_reserved,
            'after_reserved' => $this->after_reserved,
            'before_damaged' => $this->before_damaged,
            'after_damaged' => $this->after_damaged,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
