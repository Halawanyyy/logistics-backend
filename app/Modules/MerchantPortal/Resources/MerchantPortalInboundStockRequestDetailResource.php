<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalInboundStockRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'warehouse_summary' => $this->warehouse_id !== null ? [
                'id' => $this->warehouse_id,
                'name' => $this->relationLoaded('warehouse') ? $this->warehouse?->name : null,
            ] : null,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'warehouse_product_id' => $item->warehouse_product_id,
                    'product_name' => $item->warehouseProduct?->name ?? $item->requested_product_name,
                    'sku' => $item->warehouseProduct?->sku ?? $item->requested_sku,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                ])->values();
            }),
            'linked_warehouse_receipt_summary' => $this->linked_warehouse_receipt_id !== null ? [
                'id' => $this->linked_warehouse_receipt_id,
                'receipt_number' => $this->relationLoaded('linkedWarehouseReceipt') ? $this->linkedWarehouseReceipt?->receipt_number : null,
                'status' => $this->relationLoaded('linkedWarehouseReceipt') ? $this->linkedWarehouseReceipt?->status : null,
            ] : null,
            'expected_arrival_date' => $this->expected_arrival_date,
            'submitted_at' => $this->submitted_at,
            'cancelled_at' => $this->cancelled_at,
            'received_at' => $this->received_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'allowed_actions' => [
                'can_cancel' => in_array((string) $this->status, MerchantInboundStockRequestStatus::cancellableValues(), true),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
