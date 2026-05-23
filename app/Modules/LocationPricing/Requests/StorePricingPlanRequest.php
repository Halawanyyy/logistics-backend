<?php

namespace App\Modules\LocationPricing\Requests;

use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingPlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('pricing_plans', 'code')],
            'description' => ['nullable', 'string'],
            'monthly_orders_from' => ['nullable', 'integer', 'min:0'],
            'monthly_orders_to' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(PricingPlanStatus::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = $this->input('monthly_orders_from');
            $to = $this->input('monthly_orders_to');

            if ($from !== null && $to !== null && (int) $to < (int) $from) {
                $validator->errors()->add('monthly_orders_to', 'The monthly orders to field must be greater than or equal to monthly orders from.');
            }
        });
    }
}
