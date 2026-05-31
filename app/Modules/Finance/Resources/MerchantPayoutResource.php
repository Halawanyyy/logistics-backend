<?php

namespace App\Modules\Finance\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'treasury_account_id' => $this->treasury_account_id,
            'treasury_account_name' => $this->whenLoaded('treasuryAccount', fn () => $this->treasuryAccount?->name),
            'amount' => $this->amount,
            'status' => $this->status,
            'status_label' => $labels->label('finance.payout_status', $this->status),
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at,
            'rejected_by' => $this->rejected_by,
            'rejected_by_name' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->name),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->paid_at,
            'wallet_transaction_id' => $this->whenLoaded('walletTransaction', fn () => $this->walletTransaction?->id),
            'created_at' => $this->created_at,
        ];
    }
}
