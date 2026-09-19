<?php

namespace App\Modules\LocationPricing\Services;

use App\Models\User;
use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\LocationPricing\Models\PricingPlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingPlanService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?User $user = null): PricingPlan
    {
        return DB::transaction(function () use ($attributes, $user) {
            $planData = [
                'name' => trim($attributes['name']),
                'code' => trim($attributes['code']),
                'description' => isset($attributes['description']) ? trim((string) $attributes['description']) : null,
                'monthly_orders_from' => $attributes['monthly_orders_from'] ?? null,
                'monthly_orders_to' => $attributes['monthly_orders_to'] ?? null,
                'monthly_price' => $attributes['monthly_price'] ?? null,
                'yearly_price' => $attributes['yearly_price'] ?? null,
                'currency' => $attributes['currency'] ?? 'EGP',
                'is_popular' => (bool) ($attributes['is_popular'] ?? false),
                'is_custom_pricing' => (bool) ($attributes['is_custom_pricing'] ?? $attributes['requires_contact'] ?? false),
                'requires_contact' => (bool) ($attributes['requires_contact'] ?? $attributes['is_custom_pricing'] ?? false),
                'display_order' => $attributes['display_order'] ?? 0,
                'is_visible_to_merchants' => (bool) ($attributes['is_visible_to_merchants'] ?? true),
                'status' => $attributes['status'] ?? PricingPlanStatus::ACTIVE,
                'created_by' => $user?->id,
            ];

            $pricingPlan = PricingPlan::query()->create($planData);

            if (array_key_exists('features', $attributes) && is_array($attributes['features'])) {
                $this->syncFeatures($pricingPlan, $attributes['features']);
            }

            return $pricingPlan->load(['features', 'creator'])->loadCount('merchants');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PricingPlan $pricingPlan, array $attributes): PricingPlan
    {
        return DB::transaction(function () use ($pricingPlan, $attributes) {
            $planData = [];

            if (array_key_exists('name', $attributes)) {
                $planData['name'] = trim((string) $attributes['name']);
            }
            if (array_key_exists('code', $attributes)) {
                $planData['code'] = trim((string) $attributes['code']);
            }
            if (array_key_exists('description', $attributes)) {
                $planData['description'] = $attributes['description'] !== null ? trim((string) $attributes['description']) : null;
            }
            if (array_key_exists('monthly_orders_from', $attributes)) {
                $planData['monthly_orders_from'] = $attributes['monthly_orders_from'];
            }
            if (array_key_exists('monthly_orders_to', $attributes)) {
                $planData['monthly_orders_to'] = $attributes['monthly_orders_to'];
            }
            if (array_key_exists('monthly_price', $attributes)) {
                $planData['monthly_price'] = $attributes['monthly_price'];
            }
            if (array_key_exists('yearly_price', $attributes)) {
                $planData['yearly_price'] = $attributes['yearly_price'];
            }
            if (array_key_exists('currency', $attributes)) {
                $planData['currency'] = $attributes['currency'];
            }
            if (array_key_exists('is_popular', $attributes)) {
                $planData['is_popular'] = (bool) $attributes['is_popular'];
            }
            if (array_key_exists('requires_contact', $attributes) || array_key_exists('is_custom_pricing', $attributes)) {
                $reqContact = (bool) ($attributes['requires_contact'] ?? $attributes['is_custom_pricing']);
                $planData['requires_contact'] = $reqContact;
                $planData['is_custom_pricing'] = $reqContact;
            }
            if (array_key_exists('display_order', $attributes)) {
                $planData['display_order'] = (int) $attributes['display_order'];
            }
            if (array_key_exists('is_visible_to_merchants', $attributes)) {
                $planData['is_visible_to_merchants'] = (bool) $attributes['is_visible_to_merchants'];
            }
            if (array_key_exists('status', $attributes)) {
                $planData['status'] = $attributes['status'];
            }

            if (! empty($planData)) {
                $pricingPlan->update($planData);
            }

            if (array_key_exists('features', $attributes) && is_array($attributes['features'])) {
                $this->syncFeatures($pricingPlan, $attributes['features']);
            }

            return $pricingPlan->fresh(['features', 'creator'])->loadCount('merchants');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $features
     */
    public function syncFeatures(PricingPlan $pricingPlan, array $features): void
    {
        $submittedIds = [];

        foreach ($features as $index => $item) {
            $featureId = $item['id'] ?? null;
            if ($featureId) {
                $feature = PricingPlanFeature::query()->find($featureId);
                if (! $feature || (int) $feature->pricing_plan_id !== (int) $pricingPlan->id) {
                    throw ValidationException::withMessages([
                        "features.{$index}.id" => 'Feature does not belong to this pricing plan.',
                    ]);
                }
                $submittedIds[] = (int) $featureId;
            }
        }

        // Delete omitted features
        $pricingPlan->features()->whereNotIn('id', $submittedIds)->delete();

        // Update or insert features
        foreach ($features as $index => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $isIncluded = filter_var($item['is_included'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $sortOrder = isset($item['sort_order']) ? (int) $item['sort_order'] : $index;

            $featureId = $item['id'] ?? null;

            if ($featureId && in_array((int) $featureId, $submittedIds, true)) {
                $pricingPlan->features()->where('id', $featureId)->update([
                    'label' => $label,
                    'is_included' => $isIncluded,
                    'sort_order' => $sortOrder,
                ]);
            } else {
                $pricingPlan->features()->create([
                    'label' => $label,
                    'is_included' => $isIncluded,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }

    public function delete(PricingPlan $pricingPlan): void
    {
        if ($pricingPlan->merchants()->exists()) {
            throw ValidationException::withMessages([
                'plan' => 'Pricing plan cannot be deleted while assigned to merchants.',
            ]);
        }

        DB::transaction(function () use ($pricingPlan) {
            $pricingPlan->features()->delete();
            $pricingPlan->delete();
        });
    }
}
