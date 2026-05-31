<?php

namespace App\Modules\Finance\Resources;

use App\Services\LocalizedLabelService;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = app(LocalizedLabelService::class);
        $settings = app(SystemSettingService::class);
        $currency = (string) $settings->get('default_currency', 'EGP');
        $dueDays = max(0, (int) $settings->get('invoice_due_days', 7));
        $dueDateBase = $this->issued_at ?? $this->created_at;

        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant_name' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'invoice_number' => $this->invoice_number,
            'currency' => $currency !== '' ? $currency : 'EGP',
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'due_date' => $dueDateBase?->copy()->addDays($dueDays)?->toDateString(),
            'total_cod' => $this->total_cod,
            'total_shipping_fees' => $this->total_shipping_fees,
            'total_warehouse_charges' => $this->total_warehouse_charges,
            'total_payable' => $this->total_payable,
            'status' => $this->status,
            'status_label' => $labels->label('finance.invoice_status', $this->status),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'issued_at' => $this->issued_at,
            'generated_at' => $this->generated_at,
            'download_count' => $this->download_count,
            'last_downloaded_at' => $this->last_downloaded_at,
            'items' => $this->whenLoaded('items', fn () => MerchantInvoiceItemResource::collection($this->items)),
            'created_at' => $this->created_at,
        ];
    }
}
