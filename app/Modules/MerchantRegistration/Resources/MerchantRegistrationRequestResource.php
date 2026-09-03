<?php

namespace App\Modules\MerchantRegistration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantRegistrationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('primaryOwner') ? $this->primaryOwner : null;

        return [
            'merchant_id' => $this->id,
            'company_name' => $this->company_name,
            'owner_name' => $owner?->user?->name,
            'owner_email' => $owner?->user?->email,
            'phone' => $this->phone,
            'registration_date' => $this->created_at,
            'status' => $this->status,
            'pricing_plan' => $this->pricingPlan === null ? null : [
                'id' => $this->pricingPlan->id,
                'name' => $this->pricingPlan->name,
            ],
            'reviewed_at' => $this->reviewed_at,
            'reviewed_by' => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ],
            'rejection_reason' => $this->rejection_reason,
            'approval_notes' => $this->approval_notes,
        ];
    }
}
