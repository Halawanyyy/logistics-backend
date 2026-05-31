<?php

namespace App\Modules\MerchantPortal\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalWarehouseChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charge_type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'status' => $this->status,
            'warehouse_summary' => $this->warehouse_id !== null ? [
                'id' => $this->warehouse_id,
                'name' => $this->relationLoaded('warehouse') ? $this->warehouse?->name : null,
            ] : null,
            'related_product' => $this->warehouse_product_id !== null ? [
                'id' => $this->warehouse_product_id,
                'name' => $this->relationLoaded('warehouseProduct') ? $this->warehouseProduct?->name : null,
            ] : null,
            'related_order' => $this->order_id !== null ? [
                'id' => $this->order_id,
                'order_number' => $this->relationLoaded('order') ? $this->order?->order_number : null,
            ] : null,
            'charge_date' => $this->charge_date,
            'created_at' => $this->created_at,
        ];
    }
}
