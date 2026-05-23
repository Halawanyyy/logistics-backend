<?php

namespace App\Modules\LocationPricing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPlanRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pricing_plan_id' => $this->pricing_plan_id,
            'pricing_plan_name' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->name),
            'governorate_id' => $this->governorate_id,
            'governorate_name' => $this->whenLoaded('governorate', fn () => $this->governorate?->name),
            'area_id' => $this->area_id,
            'area_name' => $this->whenLoaded('area', fn () => $this->area?->name),
            'delivery_fee' => $this->delivery_fee,
            'swap_fee' => $this->swap_fee,
            'return_fee' => $this->return_fee,
            'is_active' => $this->is_active,
        ];
    }
}
