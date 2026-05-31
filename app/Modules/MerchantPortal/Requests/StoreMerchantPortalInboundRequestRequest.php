<?php

namespace App\Modules\MerchantPortal\Requests;

use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchantPortalInboundRequestRequest extends FormRequest
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
            'merchant_id' => ['prohibited'],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'expected_arrival_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.warehouse_product_id' => [
                'nullable',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.requested_product_name' => ['nullable', 'string', 'max:255'],
            'items.*.requested_sku' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $merchantId = $this->attributes->get('merchant_id');

            if (! is_numeric($merchantId)) {
                return;
            }

            $productIds = [];
            $requestedSkus = [];

            foreach ($this->input('items', []) as $index => $item) {
                $warehouseProductId = $item['warehouse_product_id'] ?? null;
                $requestedProductName = trim((string) ($item['requested_product_name'] ?? ''));
                $requestedSku = trim((string) ($item['requested_sku'] ?? ''));

                if ($warehouseProductId !== null) {
                    $product = WarehouseProduct::query()
                        ->whereKey($warehouseProductId)
                        ->where('merchant_id', (int) $merchantId)
                        ->first();

                    if ($product === null) {
                        $validator->errors()->add("items.$index.warehouse_product_id", 'The selected warehouse product does not belong to the current merchant.');
                        continue;
                    }

                    if (in_array((int) $warehouseProductId, $productIds, true)) {
                        $validator->errors()->add("items.$index.warehouse_product_id", 'Duplicate warehouse products are not allowed in the same inbound request.');
                    }

                    $productIds[] = (int) $warehouseProductId;
                    continue;
                }

                if ($requestedProductName === '') {
                    $validator->errors()->add("items.$index.requested_product_name", 'A requested product name is required when no warehouse product is selected.');
                }

                if ($requestedSku === '') {
                    $validator->errors()->add("items.$index.requested_sku", 'A requested SKU is required when no warehouse product is selected.');
                    continue;
                }

                $normalizedRequestedSku = mb_strtolower($requestedSku);

                if (in_array($normalizedRequestedSku, $requestedSkus, true)) {
                    $validator->errors()->add("items.$index.requested_sku", 'Duplicate requested SKUs are not allowed in the same inbound request.');
                    continue;
                }

                $requestedSkus[] = $normalizedRequestedSku;
            }
        });
    }
}
