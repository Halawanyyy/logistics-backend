<?php

namespace App\Modules\Shipment\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicShipmentTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        $trackingEvents = $this->relationLoaded('histories')
            ? $this->histories
                ->sortBy('created_at')
                ->values()
                ->map(fn ($history) => [
                    'status' => $history->status,
                    'current_status' => $history->status,
                    'status_label' => $labels->label('shipments.status', $history->status),
                    'created_at' => $history->created_at,
                ])
                ->values()
                ->all()
            : null;

        return [
            'shipment_number' => $this->shipment_number,
            'waybill_number' => $this->relationLoaded('waybill') ? $this->waybill?->waybill_number : null,
            'status' => $this->status,
            'current_status' => $this->status,
            'status_label' => $labels->label('shipments.status', $this->status),
            'delivery_governorate_name' => $this->whenLoaded('deliveryGovernorate', fn () => $this->deliveryGovernorate?->name),
            'delivery_area_name' => $this->whenLoaded('deliveryArea', fn () => $this->deliveryArea?->name),
            'latest_tracking_event' => $trackingEvents !== null && $trackingEvents !== []
                ? $trackingEvents[array_key_last($trackingEvents)]
                : null,
            'tracking_events' => $trackingEvents,
            'histories' => $trackingEvents,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
