<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickPackFeeRuleResource extends JsonResource
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
            'pick_fee_first_item' => $this->pick_fee_first_item,
            'pick_fee_additional_item' => $this->pick_fee_additional_item,
            'packing_fee_base' => $this->packing_fee_base,
            'fragile_extra_fee' => $this->fragile_extra_fee,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
