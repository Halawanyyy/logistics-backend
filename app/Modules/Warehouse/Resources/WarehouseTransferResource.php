<?php

namespace App\Modules\Warehouse\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'source_warehouse_id' => $this->source_warehouse_id,
            'source_warehouse_name' => $this->whenLoaded('sourceWarehouse', fn () => $this->sourceWarehouse?->name),
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'destination_warehouse_name' => $this->whenLoaded('destinationWarehouse', fn () => $this->destinationWarehouse?->name),
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.transfer_status', $this->status),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'confirmed_by' => $this->confirmed_by,
            'confirmed_by_name' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy?->name),
            'dispatched_by' => $this->dispatched_by,
            'dispatched_by_name' => $this->whenLoaded('dispatchedBy', fn () => $this->dispatchedBy?->name),
            'received_by' => $this->received_by,
            'received_by_name' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy?->name),
            'confirmed_at' => $this->confirmed_at,
            'dispatched_at' => $this->dispatched_at,
            'received_at' => $this->received_at,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => WarehouseTransferItemResource::collection($this->items)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
