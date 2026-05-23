<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'amount' => $this->amount,
            'status' => $this->status,
            'treasury_account_id' => $this->treasury_account_id,
            'treasury_account_name' => $this->whenLoaded('treasuryAccount', fn () => $this->treasuryAccount?->name),
            'expense_date' => $this->expense_date,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at,
            'paid_by' => $this->paid_by,
            'paid_by_name' => $this->whenLoaded('paidBy', fn () => $this->paidBy?->name),
            'paid_at' => $this->paid_at,
            'rejected_by' => $this->rejected_by,
            'rejected_by_name' => $this->whenLoaded('rejectedBy', fn () => $this->rejectedBy?->name),
            'rejected_at' => $this->rejected_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
        ];
    }
}
