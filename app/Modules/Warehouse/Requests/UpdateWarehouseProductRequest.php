<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\WarehouseProductStatus;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Services\BarcodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseProductRequest extends FormRequest
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
            'merchant_id' => [
                'sometimes',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'barcode_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit_weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_fragile' => ['sometimes', 'boolean'],
            'requires_packaging' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(WarehouseProductStatus::values())],
            'minimum_stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'low_stock_alert_enabled' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var WarehouseProduct $product */
            $product = $this->route('warehouseProduct');
            $merchantId = (int) $this->input('merchant_id', $product->merchant_id);
            $sku = $this->has('sku') ? $this->input('sku') : $product->sku;
            $barcode = $this->has('barcode') ? $this->input('barcode') : $product->barcode;

            if ($sku !== null) {
                $duplicateSku = WarehouseProduct::query()
                    ->where('merchant_id', $merchantId)
                    ->where('sku', $sku)
                    ->whereKeyNot($product->id)
                    ->exists();

                if ($duplicateSku) {
                    $validator->errors()->add('sku', 'The sku has already been taken for this merchant.');
                }
            }

            if ($barcode !== null) {
                /** @var BarcodeService $barcodeService */
                $barcodeService = app(BarcodeService::class);
                $duplicateBarcode = ! $barcodeService->isUnique($barcode, 'warehouse_product', $product->id);

                if ($duplicateBarcode) {
                    $validator->errors()->add('barcode', 'The barcode has already been taken.');
                }
            }
        });
    }
}
