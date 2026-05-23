<?php

namespace App\Modules\Merchant\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'status' => $this->status,
            'pricing_plan_id' => $this->pricing_plan_id,
            'pricing_plan_name' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->name),
            'pricing_plan_code' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->code),
            'created_at' => $this->created_at,
        ];
    }
}
