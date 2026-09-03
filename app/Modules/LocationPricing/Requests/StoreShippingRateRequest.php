<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\ShippingRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingRateRequest extends FormRequest
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
            'shipping_fee' => ['required', 'numeric', 'min:0'],
            'swap_fee' => ['required', 'numeric', 'min:0'],
            'return_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $governorateId = (int) $this->input('governorate_id');
            $areaId = $this->input('area_id');

            if ($areaId !== null) {
                $areaBelongsToGovernorate = Area::query()
                    ->whereKey($areaId)
                    ->where('governorate_id', $governorateId)
                    ->exists();

                if (! $areaBelongsToGovernorate) {
                    $validator->errors()->add('area_id', 'The selected area does not belong to the selected governorate.');
                    return;
                }
            }

            $duplicateRate = ShippingRate::query()
                ->where('governorate_id', $governorateId)
                ->when($areaId !== null, fn ($query) => $query->where('area_id', $areaId))
                ->when($areaId === null, fn ($query) => $query->whereNull('area_id'))
                ->exists();

            if ($duplicateRate) {
                $validator->errors()->add('area_id', 'A shipping rate already exists for this location.');
            }
        });
    }
}
