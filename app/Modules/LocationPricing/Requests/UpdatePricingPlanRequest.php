<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingPlanRequest extends FormRequest
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
        $pricingPlan = $this->route('pricingPlan');
        $pricingPlanId = $pricingPlan?->id ?? $pricingPlan;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('pricing_plans', 'code')->ignore($pricingPlanId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'monthly_orders_from' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_orders_to' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(PricingPlanStatus::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $pricingPlan = $this->route('pricingPlan');
            $from = $this->has('monthly_orders_from')
                ? $this->input('monthly_orders_from')
                : $pricingPlan->monthly_orders_from;
            $to = $this->has('monthly_orders_to')
                ? $this->input('monthly_orders_to')
                : $pricingPlan->monthly_orders_to;

            if ($from !== null && $to !== null && (int) $to < (int) $from) {
                $validator->errors()->add('monthly_orders_to', 'The monthly orders to field must be greater than or equal to monthly orders from.');
            }
        });
    }
}
