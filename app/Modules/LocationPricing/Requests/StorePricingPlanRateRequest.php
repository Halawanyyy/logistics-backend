<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\PricingPlanRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingPlanRateRequest extends FormRequest
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
            'pricing_plan_id' => [
                'required',
                Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'governorate_id' => [
                'required',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'swap_fee' => ['nullable', 'numeric', 'min:0'],
            'return_fee' => ['nullable', 'numeric', 'min:0'],
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
            $pricingPlanId = (int) $this->input('pricing_plan_id');

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

            $duplicateRate = PricingPlanRate::query()
                ->where('pricing_plan_id', $pricingPlanId)
                ->where('governorate_id', $governorateId)
                ->when($areaId !== null, fn ($query) => $query->where('area_id', $areaId))
                ->when($areaId === null, fn ($query) => $query->whereNull('area_id'))
                ->exists();

            if ($duplicateRate) {
                $validator->errors()->add('area_id', 'A pricing plan rate already exists for this location.');
            }
        });
    }
}
