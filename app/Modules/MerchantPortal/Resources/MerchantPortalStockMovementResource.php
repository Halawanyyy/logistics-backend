<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Modules\Warehouse\Enums\StockMovementType;
use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalStockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'movement_type' => $this->type,
            'movement_type_label' => $labels->label('warehouse.stock_movement_type', $this->type),
            'quantity' => $this->quantity,
            'warehouse_summary' => [
                'id' => $this->warehouse_id,
                'name' => $this->relationLoaded('warehouse') ? $this->warehouse?->name : null,
            ],
            'product_summary' => [
                'id' => $this->warehouse_product_id,
                'name' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->name : null,
                'sku' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->sku : null,
                'barcode' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->barcode : null,
            ],
            'reference_summary' => [
                'reference_type' => $this->reference_type,
                'reference_id' => $this->reference_id,
                'order_id' => $this->order_id,
                'order_number' => $this->relationLoaded('order') ? $this->order?->order_number : null,
                'shipment_id' => $this->shipment_id,
                'shipment_number' => $this->relationLoaded('shipment') ? $this->shipment?->shipment_number : null,
            ],
            'notes' => $this->type === StockMovementType::ADJUSTMENT ? null : $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
