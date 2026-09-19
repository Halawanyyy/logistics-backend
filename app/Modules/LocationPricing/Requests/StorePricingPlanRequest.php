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
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_orders_from' => ['nullable', 'integer', 'min:0'],
            'monthly_orders_to' => ['nullable', 'integer', 'min:0'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'features' => ['sometimes', 'nullable', 'array', 'max:50'],
            'features.*.id' => ['nullable'],
            'features.*.label' => ['required', 'string', 'max:255'],
            'features.*.is_included' => ['required', 'boolean'],
            'features.*.sort_order' => ['required', 'integer', 'min:0'],
            'is_popular' => ['nullable', 'boolean'],
            'is_custom_pricing' => ['nullable', 'boolean'],
            'requires_contact' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_visible_to_merchants' => ['nullable', 'boolean'],
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

            $features = $this->input('features');
            if (is_array($features)) {
                $seenLabels = [];
                foreach ($features as $index => $item) {
                    $label = isset($item['label']) ? trim((string) $item['label']) : '';
                    if ($label === '') {
                        $validator->errors()->add("features.{$index}.label", 'The feature label cannot be empty.');
                        continue;
                    }

                    $normalized = mb_strtolower($label);
                    if (in_array($normalized, $seenLabels, true)) {
                        $validator->errors()->add("features.{$index}.label", 'Duplicate feature labels are not allowed.');
                    } else {
                        $seenLabels[] = $normalized;
                    }
                }
            }
        });
    }
}
