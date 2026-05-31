<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalInvoiceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $isPaid = (string) $this->status === 'paid';

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'status_label' => $labels->label('finance.invoice_status', $this->status),
            'currency' => $this->currency ?? 'EGP',
            'period' => [
                'from' => $this->period_start,
                'to' => $this->period_end,
            ],
            'totals' => [
                'total_cod' => $this->total_cod,
                'total_shipping_fees' => $this->total_shipping_fees,
                'total_warehouse_charges' => $this->total_warehouse_charges,
                'total' => $this->total_payable,
                'paid_amount' => $isPaid ? $this->total_payable : '0.00',
                'remaining_amount' => $isPaid ? '0.00' : $this->total_payable,
            ],
            'issued_at' => $this->issued_at,
            'due_at' => $this->due_date ?? null,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'total_amount' => $item->total_amount,
                ])->values();
            }),
            'can_preview' => true,
            'can_download' => true,
            'created_at' => $this->created_at,
        ];
    }
}
