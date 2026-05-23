<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasuryTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'treasury_account_id' => $this->treasury_account_id,
            'treasury_account_name' => $this->whenLoaded('treasuryAccount', fn () => $this->treasuryAccount?->name),
            'treasury_account_code' => $this->whenLoaded('treasuryAccount', fn () => $this->treasuryAccount?->code),
            'transaction_number' => $this->transaction_number,
            'direction' => $this->direction,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
