<?php

namespace App\Modules\Finance\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantWalletTransactionResource extends JsonResource
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
            'transaction_number' => $this->transaction_number,
            'transaction_type' => $this->transaction_type,
            'type_label' => $labels->label('finance.wallet_transaction_type', $this->transaction_type),
            'direction' => $this->direction,
            'direction_label' => $labels->label('finance.wallet_transaction_direction', $this->direction),
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'status_label' => $labels->label('finance.wallet_transaction_status', $this->status),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'reference_number' => $this->reference_number,
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at,
            'rejected_by' => $this->rejected_by,
            'rejected_by_name' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->name),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
