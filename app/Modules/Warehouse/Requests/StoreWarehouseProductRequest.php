<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Enums\WarehouseProductStatus;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Services\BarcodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseProductRequest extends FormRequest
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
                'required',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_weight' => ['nullable', 'numeric', 'min:0'],
            'unit_length' => ['nullable', 'numeric', 'min:0'],
            'unit_width' => ['nullable', 'numeric', 'min:0'],
            'unit_height' => ['nullable', 'numeric', 'min:0'],
            'is_fragile' => ['nullable', 'boolean'],
            'requires_packaging' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(WarehouseProductStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $merchantId = (int) $this->input('merchant_id');
            $sku = $this->input('sku');
            $barcode = $this->input('barcode');

            if ($sku !== null) {
                $duplicateSku = WarehouseProduct::query()
                    ->where('merchant_id', $merchantId)
                    ->where('sku', $sku)
                    ->exists();

                if ($duplicateSku) {
                    $validator->errors()->add('sku', 'The sku has already been taken for this merchant.');
                }
            }

            if ($barcode !== null) {
                /** @var BarcodeService $barcodeService */
                $barcodeService = app(BarcodeService::class);
                $duplicateBarcode = ! $barcodeService->isUnique($barcode);

                if ($duplicateBarcode) {
                    $validator->errors()->add('barcode', 'The barcode has already been taken.');
                }
            }
        });
    }
}
