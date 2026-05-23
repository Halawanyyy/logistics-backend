<?php

namespace App\Modules\LocationPricing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPricingPlanRatesRequest extends FormRequest
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
                'nullable',
                Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'governorate_id' => [
                'nullable',
                Rule::exists('governorates', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
