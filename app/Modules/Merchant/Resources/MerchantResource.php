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
            'governorate_id' => $this->governorate_id,
            'area_id' => $this->area_id,
            'status' => $this->status,
            'registration_source' => $this->registration_source,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'rejection_reason' => $this->rejection_reason,
            'approval_notes' => $this->approval_notes,
            'pricing_plan_id' => $this->pricing_plan_id,
            'pricing_plan_name' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->name),
            'pricing_plan_code' => $this->whenLoaded('pricingPlan', fn () => $this->pricingPlan?->code),
            'created_at' => $this->created_at,
        ];
    }
}
