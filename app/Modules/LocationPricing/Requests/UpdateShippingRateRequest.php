<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\ShippingRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingRateRequest extends FormRequest
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
                'sometimes',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'shipping_fee' => ['sometimes', 'numeric', 'min:0'],
            'swap_fee' => ['sometimes', 'numeric', 'min:0'],
            'return_fee' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $shippingRate = $this->route('shippingRate');
            $governorateId = (int) $this->input('governorate_id', $shippingRate->governorate_id);
            $areaId = $this->has('area_id') ? $this->input('area_id') : $shippingRate->area_id;

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
                ->whereKeyNot($shippingRate->id)
                ->exists();

            if ($duplicateRate) {
                $validator->errors()->add('area_id', 'A shipping rate already exists for this location.');
            }
        });
    }
}
