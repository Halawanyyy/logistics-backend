<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalWalletTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);

        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'type' => $this->transaction_type,
            'type_label' => $labels->label('finance.wallet_transaction_type', $this->transaction_type),
            'direction' => $this->direction,
            'direction_label' => $labels->label('finance.wallet_transaction_direction', $this->direction),
            'status' => $this->status,
            'status_label' => $labels->label('finance.wallet_transaction_status', $this->status),
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'description' => $this->reason,
            'reference_summary' => [
                'reference_number' => $this->reference_number,
                'reference_type' => $this->source_type,
                'reference_id' => $this->source_id,
            ],
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];
    }
}
