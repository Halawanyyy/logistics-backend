<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'payout_reference' => $this->reference_number,
            'status' => $this->status,
            'status_label' => $labels->label('finance.payout_status', $this->status),
            'amount' => $this->amount,
            'method' => $this->payment_method,
            'requested_at' => $this->created_at,
            'approved_at' => $this->approved_at,
            'completed_at' => $this->paid_at,
            'rejected_at' => $this->rejected_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
