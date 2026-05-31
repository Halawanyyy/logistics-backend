<?php

namespace App\Modules\MerchantPortal\Resources;

use App\Services\LocalizedLabelService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantPortalInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $isPaid = (string) $this->status === 'paid';
        $remainingAmount = $isPaid ? '0.00' : $this->total_payable;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'status_label' => $labels->label('finance.invoice_status', $this->status),
            'period_from' => $this->period_start,
            'period_to' => $this->period_end,
            'subtotal' => $this->total_payable,
            'total' => $this->total_payable,
            'paid_amount' => $isPaid ? $this->total_payable : '0.00',
            'remaining_amount' => $remainingAmount,
            'issued_at' => $this->issued_at,
            'due_at' => $this->due_date ?? null,
            'created_at' => $this->created_at,
            'can_preview' => true,
            'can_download' => true,
            'preview_url' => '/api/merchant-portal/finance/invoices/'.$this->id.'/preview',
            'download_url' => '/api/merchant-portal/finance/invoices/'.$this->id.'/download',
        ];
    }
}
