<?php

namespace Database\Seeders;

use App\Modules\LocationPricing\Models\PricingPlan;
use Illuminate\Database\Seeder;

class PricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'code' => 'starter',
                'description' => 'Essential shipping tools for new and growing merchants.',
                'monthly_orders_from' => 0,
                'monthly_orders_to' => 500,
                'monthly_price' => 499,
                'yearly_price' => 4790.40,
                'currency' => 'EGP',
                'features' => [
                    'Up to 500 orders per month',
                    'Shipment tracking',
                    'Merchant wallet and invoices',
                    'Standard support',
                ],
                'is_popular' => false,
                'is_custom_pricing' => false,
                'display_order' => 1,
                'is_visible_to_merchants' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Growth',
                'code' => 'growth',
                'description' => 'Advanced fulfillment and reporting for scaling operations.',
                'monthly_orders_from' => 501,
                'monthly_orders_to' => 2000,
                'monthly_price' => 999,
                'yearly_price' => 9590.40,
                'currency' => 'EGP',
                'features' => [
                    'Up to 2,000 orders per month',
                    'Warehouse inventory management',
                    'Advanced finance reports',
                    'Priority support',
                ],
                'is_popular' => true,
                'is_custom_pricing' => false,
                'display_order' => 2,
                'is_visible_to_merchants' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Enterprise',
                'code' => 'enterprise',
                'description' => 'A tailored logistics package for high-volume merchants.',
                'monthly_orders_from' => 2001,
                'monthly_orders_to' => null,
                'monthly_price' => null,
                'yearly_price' => null,
                'currency' => 'EGP',
                'features' => [
                    'Unlimited order volume',
                    'Custom pricing and workflows',
                    'Dedicated account manager',
                    'Premium support',
                ],
                'is_popular' => false,
                'is_custom_pricing' => true,
                'display_order' => 3,
                'is_visible_to_merchants' => true,
                'status' => 'active',
            ],
        ];

        foreach ($plans as $data) {
            $plan = PricingPlan::query()
                ->withTrashed()
                ->firstOrNew(['code' => $data['code']]);
            $features = $data['features'];
            unset($data['features']);

            $plan->fill($data);

            if ($plan->trashed()) {
                $plan->restore();
            }

            $plan->save();
        }
    }
}
