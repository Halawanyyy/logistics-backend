<?php

namespace App\Modules\LocationPricing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'governorate_name' => $this->whenLoaded('governorate', fn () => $this->governorate?->name),
            'area_id' => $this->area_id,
            'area_name' => $this->whenLoaded('area', fn () => $this->area?->name),
            'shipping_fee' => $this->shipping_fee,
            'swap_fee' => $this->swap_fee,
            'return_fee' => $this->return_fee,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
