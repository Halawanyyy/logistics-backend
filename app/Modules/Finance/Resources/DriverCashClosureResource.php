<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverCashClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'driver_name' => $this->whenLoaded('driver', fn () => $this->driver?->name),
            'driver_manifest_id' => $this->driver_manifest_id,
            'manifest_number' => $this->whenLoaded('driverManifest', fn () => $this->driverManifest?->manifest_number),
            'treasury_account_id' => $this->treasury_account_id,
            'treasury_account_name' => $this->whenLoaded('treasuryAccount', fn () => $this->treasuryAccount?->name),
            'date' => $this->closure_date?->toDateString(),
            'expected_amount' => $this->expected_amount,
            'received_amount' => $this->received_amount,
            'difference_amount' => $this->difference_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'verified_by' => $this->verified_by,
            'verified_by_name' => $this->whenLoaded('verifiedBy', fn () => $this->verifiedBy?->name),
            'verified_at' => $this->verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
