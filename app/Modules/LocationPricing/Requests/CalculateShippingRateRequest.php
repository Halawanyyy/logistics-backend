<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\Order\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateShippingRateRequest extends FormRequest
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
            'governorate_id' => [
                'required',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'merchant_id' => [
                'nullable',
                Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'order_type' => ['nullable', Rule::in(OrderType::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('area_id') === null) {
                return;
            }

            $belongsToGovernorate = Area::query()
                ->whereKey($this->input('area_id'))
                ->where('governorate_id', $this->input('governorate_id'))
                ->exists();

            if (! $belongsToGovernorate) {
                $validator->errors()->add('area_id', 'The selected area does not belong to the selected governorate.');
            }
        });
    }
}
