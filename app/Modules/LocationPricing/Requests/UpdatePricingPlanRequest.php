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
        $pricingPlanId = $pricingPlan instanceof \App\Modules\LocationPricing\Models\PricingPlan
            ? $pricingPlan->id
            : (is_numeric($pricingPlan) ? (int) $pricingPlan : null);

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('pricing_plans', 'code')->ignore($pricingPlanId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'monthly_orders_from' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_orders_to' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'monthly_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'yearly_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:3'],
            'features' => ['sometimes', 'nullable', 'array', 'max:50'],
            'features.*.id' => ['nullable'],
            'features.*.label' => ['required', 'string', 'max:255'],
            'features.*.is_included' => ['required', 'boolean'],
            'features.*.sort_order' => ['required', 'integer', 'min:0'],
            'is_popular' => ['sometimes', 'nullable', 'boolean'],
            'is_custom_pricing' => ['sometimes', 'nullable', 'boolean'],
            'requires_contact' => ['sometimes', 'nullable', 'boolean'],
            'display_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_visible_to_merchants' => ['sometimes', 'nullable', 'boolean'],
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
            $planModel = $pricingPlan instanceof \App\Modules\LocationPricing\Models\PricingPlan
                ? $pricingPlan
                : \App\Modules\LocationPricing\Models\PricingPlan::find($pricingPlan);

            $from = $this->has('monthly_orders_from')
                ? $this->input('monthly_orders_from')
                : $planModel?->monthly_orders_from;
            $to = $this->has('monthly_orders_to')
                ? $this->input('monthly_orders_to')
                : $planModel?->monthly_orders_to;

            if ($from !== null && $to !== null && (int) $to < (int) $from) {
                $validator->errors()->add('monthly_orders_to', 'The monthly orders to field must be greater than or equal to monthly orders from.');
            }

            $features = $this->input('features');
            if (is_array($features) && $planModel) {
                $seenLabels = [];
                foreach ($features as $index => $item) {
                    if (! empty($item['id'])) {
                        $belongs = \App\Modules\LocationPricing\Models\PricingPlanFeature::query()
                            ->where('id', $item['id'])
                            ->where('pricing_plan_id', $planModel->id)
                            ->exists();
                        if (! $belongs) {
                            $validator->errors()->add("features.{$index}.id", 'Feature does not belong to this pricing plan.');
                        }
                    }

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
