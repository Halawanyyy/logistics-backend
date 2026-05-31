<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                $rejected = array_key_exists('rejected_quantity', $item) ? (int) $item['rejected_quantity'] : 0;
                $accepted = array_key_exists('accepted_quantity', $item) ? (int) $item['accepted_quantity'] : max($quantity - $rejected, 0);

                $item['accepted_quantity'] = $accepted;
                $item['rejected_quantity'] = $rejected;

                return $item;
            })
            ->all();

        $this->merge(['items' => $items]);
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
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.warehouse_product_id' => [
                'required',
                Rule::exists('warehouse_products', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.accepted_quantity' => ['required', 'integer', 'min:0'],
            'items.*.rejected_quantity' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $merchantId = $this->integer('merchant_id');
            $productIds = collect($this->input('items', []))
                ->pluck('warehouse_product_id')
                ->filter()
                ->unique()
                ->values();

            $matchingProducts = WarehouseProduct::query()
                ->whereIn('id', $productIds)
                ->where('merchant_id', $merchantId)
                ->count();

            if ($matchingProducts !== $productIds->count()) {
                $validator->errors()->add('items', 'Every warehouse product must belong to the selected merchant.');
            }

            foreach ($this->input('items', []) as $index => $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $accepted = (int) ($item['accepted_quantity'] ?? 0);
                $rejected = (int) ($item['rejected_quantity'] ?? 0);

                if ($accepted + $rejected > $quantity) {
                    $validator->errors()->add("items.$index.accepted_quantity", 'Accepted quantity plus rejected quantity must not exceed quantity.');
                }
            }
        });
    }
}
