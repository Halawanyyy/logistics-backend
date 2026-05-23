<?php

namespace App\Modules\Order\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'delivery_status' => $this->delivery_status,
            'delivered_quantity' => $this->delivered_quantity,
            'returned_quantity' => $this->returned_quantity,
            'damaged_quantity' => $this->damaged_quantity,
            'unit_price' => $this->unit_price,
            'weight' => $this->weight,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
