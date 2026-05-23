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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'monthly_orders_from' => $this->monthly_orders_from,
            'monthly_orders_to' => $this->monthly_orders_to,
            'status' => $this->status,
            'merchants_count' => $this->whenCounted('merchants'),
            'created_at' => $this->created_at,
        ];
    }
}
