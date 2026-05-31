<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalInboundStockRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'status_label' => $labels->label('warehouse.inbound_request_status', $this->status),
            'warehouse_summary' => $this->warehouse_id !== null ? [
                'id' => $this->warehouse_id,
                'name' => $this->relationLoaded('warehouse') ? $this->warehouse?->name : null,
            ] : null,
            'items_count' => $items->count(),
            'total_requested_quantity' => (int) $items->sum('quantity'),
            'expected_arrival_date' => $this->expected_arrival_date,
            'submitted_at' => $this->submitted_at,
            'cancelled_at' => $this->cancelled_at,
            'received_at' => $this->received_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
        ];
    }
}
