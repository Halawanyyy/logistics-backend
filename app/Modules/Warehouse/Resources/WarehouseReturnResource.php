<?php

namespace App\Modules\Warehouse\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'order_id' => $this->order_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'warehouse_product_id' => $this->warehouse_product_id,
            'product_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->name),
            'merchant_id' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant_id),
            'merchant_name' => $this->whenLoaded('warehouseProduct', fn () => $this->warehouseProduct?->merchant?->name),
            'quantity' => $this->quantity,
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.return_status', $this->status),
            'condition' => $this->condition,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'received_at' => $this->received_at,
            'inspected_at' => $this->inspected_at,
            'restocked_at' => $this->restocked_at,
            'damaged_at' => $this->damaged_at,
            'rejected_at' => $this->rejected_at,
            'closed_at' => $this->closed_at,
            'items' => $this->whenLoaded('items', fn () => WarehouseReturnItemResource::collection($this->items)),
            'created_at' => $this->created_at,
        ];
    }
}
