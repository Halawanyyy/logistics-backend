<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorageFeeRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'pricing_plan_id' => $this->pricing_plan_id,
            'pricing_plan_name' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->name),
            'name' => $this->name,
            'calculation_type' => $this->calculation_type,
            'free_units' => $this->free_units,
            'unit_price' => $this->unit_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
