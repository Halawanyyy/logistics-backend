<?php

namespace App\Modules\Warehouse\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcode_type,
            'barcode_generated_at' => $this->barcode_generated_at,
            'description' => $this->description,
            'unit_weight' => $this->unit_weight,
            'unit_length' => $this->unit_length,
            'unit_width' => $this->unit_width,
            'unit_height' => $this->unit_height,
            'is_fragile' => (bool) $this->is_fragile,
            'requires_packaging' => (bool) $this->requires_packaging,
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.product_status', $this->status),
            'minimum_stock_quantity' => $this->minimum_stock_quantity,
            'low_stock_alert_enabled' => (bool) $this->low_stock_alert_enabled,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
