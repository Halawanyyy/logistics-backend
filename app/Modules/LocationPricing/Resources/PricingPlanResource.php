<?php

namespace App\Modules\LocationPricing\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $features = $this->relationLoaded('features')
            ? $this->getRelation('features')
            : $this->features()->get();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'monthly_orders_from' => $this->monthly_orders_from,
            'monthly_orders_to' => $this->monthly_orders_to,
            'monthly_price' => $this->monthly_price !== null ? (float) $this->monthly_price : null,
            'yearly_price' => $this->yearly_price !== null ? (float) $this->yearly_price : null,
            'price' => $this->monthly_price !== null ? (float) $this->monthly_price : null,
            'currency' => $this->currency ?? 'EGP',
            'billing_period' => 'month',
            'requires_contact' => (bool) ($this->requires_contact ?? $this->is_custom_pricing ?? false),
            'is_custom_pricing' => (bool) ($this->is_custom_pricing ?? $this->requires_contact ?? false),
            'is_popular' => (bool) $this->is_popular,
            'is_active' => $this->status === 'active',
            'sort_order' => (int) ($this->display_order ?? 0),
            'display_order' => (int) ($this->display_order ?? 0),
            'is_visible_to_merchants' => (bool) ($this->is_visible_to_merchants ?? true),
            'status' => $this->status,
            'merchants_count' => $this->whenCounted('merchants'),
            'features' => PricingPlanFeatureResource::collection($features ?? collect([])),
            'created_at' => $this->created_at,
        ];
    }
}
