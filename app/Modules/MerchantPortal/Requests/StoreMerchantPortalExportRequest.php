<?php

namespace App\Modules\MerchantPortal\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPortalExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
                'orders',
                'shipments',
                'warehouse_inventory',
                'stock_movements',
                'wallet_transactions',
                'invoices',
                'payouts',
                'inbound_stock_requests',
            ])],
            'filters' => ['nullable', 'array'],
            'merchant_id' => ['prohibited'],
        ];
    }
}
