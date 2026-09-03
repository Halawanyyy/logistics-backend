<?php

namespace App\Modules\MerchantRegistration\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantRegistrationRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->primaryOwner;

        return [
            'merchant_id' => $this->id,
            'company' => [
                'name' => $this->company_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'governorate' => $this->governorate === null ? null : [
                    'id' => $this->governorate->id,
                    'name' => $this->governorate->name,
                ],
                'area' => $this->area === null ? null : [
                    'id' => $this->area->id,
                    'name' => $this->area->name,
                ],
            ],
            'owner' => $owner === null ? null : [
                'id' => $owner->id,
                'name' => $owner->user?->name,
                'email' => $owner->user?->email,
                'phone' => null,
                'status' => $owner->status,
                'role' => $owner->role,
            ],
            'registration' => [
                'registered_at' => $this->created_at,
                'source' => $this->registration_source,
                'status' => $this->status,
            ],
            'decision' => [
                'reviewed_by' => $this->reviewer === null ? null : [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ],
                'reviewed_at' => $this->reviewed_at,
                'rejection_reason' => $this->rejection_reason,
                'approval_notes' => $this->approval_notes,
                'pricing_plan' => $this->pricingPlan === null ? null : [
                    'id' => $this->pricingPlan->id,
                    'name' => $this->pricingPlan->name,
                ],
            ],
        ];
    }
}
