<?php

namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPackagingItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_catalog_item_id' => $this->company_catalog_item_id,
            'item_name' => $this->item_name,
            'item_sku' => $this->item_sku,
            'category' => $this->category,
            'size_label' => $this->size_label,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'notes' => $this->notes,
        ];
    }
}
