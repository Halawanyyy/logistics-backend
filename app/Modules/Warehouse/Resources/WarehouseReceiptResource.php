<?php

namespace App\Modules\Warehouse\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.receipt_status', $this->status),
            'received_by' => $this->received_by,
            'received_by_name' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'confirmed_by' => $this->confirmed_by,
            'confirmed_by_name' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy?->name),
            'received_at' => $this->received_at,
            'confirmed_at' => $this->confirmed_at,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => WarehouseReceiptItemResource::collection($this->items)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
