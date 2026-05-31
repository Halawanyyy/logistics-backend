<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'shipment_number' => $this->shipment_number,
            'order_number' => $this->relationLoaded('order') ? $this->order?->order_number : null,
            'status' => $this->status,
            'status_label' => $labels->label('shipments.status', $this->status),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'delivery_location_summary' => [
                'governorate_name' => $this->relationLoaded('deliveryGovernorate') ? $this->deliveryGovernorate?->name : null,
                'area_name' => $this->relationLoaded('deliveryArea') ? $this->deliveryArea?->name : null,
                'address' => $this->delivery_address,
            ],
            'tracking_events' => $this->whenLoaded('histories', function () use ($labels) {
                return $this->histories->map(fn ($history) => [
                    'status' => $history->status,
                    'status_label' => $labels->label('shipments.status', $history->status),
                    'notes' => $history->notes,
                    'created_at' => $history->created_at,
                ])->values();
            }),
        ];
    }
}
