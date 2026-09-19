<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Modules\LocationPricing\Enums\PricingPlanStatus;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\LocationPricing\Models\PricingPlanFeature;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class PricingPlanDynamicFeaturesTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_admin_can_create_plan_with_description_and_ordered_features(): void
    {
        $response = $this->postJson('/api/pricing-plans', [
            'name' => 'Growth',
            'code' => 'growth-test',
            'description' => 'Advanced fulfillment and reporting for scaling operations.',
            'monthly_orders_from' => 501,
            'monthly_orders_to' => 2000,
            'monthly_price' => 999,
            'yearly_price' => 9590,
            'currency' => 'EGP',
            'is_popular' => true,
            'requires_contact' => false,
            'display_order' => 2,
            'status' => 'active',
            'features' => [
                [
                    'label' => 'Up to 2,000 orders per month',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
                [
                    'label' => 'Dedicated account manager',
                    'is_included' => false,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Growth')
            ->assertJsonPath('data.description', 'Advanced fulfillment and reporting for scaling operations.')
            ->assertJsonPath('data.requires_contact', false)
            ->assertJsonPath('data.is_popular', true)
            ->assertJsonPath('data.features.0.label', 'Up to 2,000 orders per month')
            ->assertJsonPath('data.features.0.is_included', true)
            ->assertJsonPath('data.features.0.sort_order', 0)
            ->assertJsonPath('data.features.1.label', 'Dedicated account manager')
            ->assertJsonPath('data.features.1.is_included', false)
            ->assertJsonPath('data.features.1.sort_order', 1);

        $this->assertDatabaseHas('pricing_plans', [
            'code' => 'growth-test',
            'description' => 'Advanced fulfillment and reporting for scaling operations.',
        ]);

        $this->assertDatabaseHas('pricing_plan_features', [
            'label' => 'Up to 2,000 orders per month',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('pricing_plan_features', [
            'label' => 'Dedicated account manager',
            'is_included' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_update_plan_features_and_toggle_included(): void
    {
        $plan = PricingPlan::create([
            'name' => 'Starter',
            'code' => 'starter-test',
            'description' => 'Initial description',
            'status' => 'active',
        ]);

        $feature1 = $plan->features()->create([
            'label' => 'Feature 1',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $feature2 = $plan->features()->create([
            'label' => 'Feature 2',
            'is_included' => false,
            'sort_order' => 1,
        ]);

        $response = $this->putJson("/api/pricing-plans/{$plan->id}", [
            'name' => 'Starter Updated',
            'description' => 'Updated description',
            'features' => [
                [
                    'id' => $feature1->id,
                    'label' => 'Feature 1 Updated',
                    'is_included' => false,
                    'sort_order' => 1,
                ],
                [
                    'id' => $feature2->id,
                    'label' => 'Feature 2 Updated',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
                [
                    'label' => 'New Feature 3',
                    'is_included' => true,
                    'sort_order' => 2,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Starter Updated')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.features.0.label', 'Feature 2 Updated')
            ->assertJsonPath('data.features.0.is_included', true)
            ->assertJsonPath('data.features.1.label', 'Feature 1 Updated')
            ->assertJsonPath('data.features.1.is_included', false)
            ->assertJsonPath('data.features.2.label', 'New Feature 3')
            ->assertJsonPath('data.features.2.is_included', true);

        $this->assertDatabaseHas('pricing_plan_features', [
            'id' => $feature1->id,
            'label' => 'Feature 1 Updated',
            'is_included' => false,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('pricing_plan_features', [
            'id' => $feature2->id,
            'label' => 'Feature 2 Updated',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('pricing_plan_features', [
            'pricing_plan_id' => $plan->id,
            'label' => 'New Feature 3',
            'is_included' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_remove_features_during_update(): void
    {
        $plan = PricingPlan::create([
            'name' => 'Basic',
            'code' => 'basic-test',
            'status' => 'active',
        ]);

        $keepFeature = $plan->features()->create([
            'label' => 'Keep Me',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $removeFeature = $plan->features()->create([
            'label' => 'Remove Me',
            'is_included' => true,
            'sort_order' => 1,
        ]);

        $response = $this->putJson("/api/pricing-plans/{$plan->id}", [
            'features' => [
                [
                    'id' => $keepFeature->id,
                    'label' => 'Keep Me',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('pricing_plan_features', ['id' => $keepFeature->id]);
        $this->assertDatabaseMissing('pricing_plan_features', ['id' => $removeFeature->id]);
    }

    public function test_partial_update_without_features_preserves_existing_features(): void
    {
        $plan = PricingPlan::create([
            'name' => 'Basic',
            'code' => 'basic-patch-test',
            'status' => 'active',
        ]);

        $feature = $plan->features()->create([
            'label' => 'Existing Feature',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $response = $this->putJson("/api/pricing-plans/{$plan->id}", [
            'name' => 'Basic Renamed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Basic Renamed')
            ->assertJsonPath('data.features.0.label', 'Existing Feature');

        $this->assertDatabaseHas('pricing_plan_features', [
            'id' => $feature->id,
            'label' => 'Existing Feature',
        ]);
    }

    public function test_feature_ownership_protection_rejects_foreign_feature_id(): void
    {
        $planA = PricingPlan::create([
            'name' => 'Plan A',
            'code' => 'plan-a',
            'status' => 'active',
        ]);

        $planB = PricingPlan::create([
            'name' => 'Plan B',
            'code' => 'plan-b',
            'status' => 'active',
        ]);

        $foreignFeature = $planB->features()->create([
            'label' => 'Foreign Feature',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $response = $this->putJson("/api/pricing-plans/{$planA->id}", [
            'features' => [
                [
                    'id' => $foreignFeature->id,
                    'label' => 'Tampered Feature',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['features.0.id']);
    }

    public function test_validation_rejects_empty_and_duplicate_feature_labels(): void
    {
        $response = $this->postJson('/api/pricing-plans', [
            'name' => 'Invalid Plan',
            'code' => 'invalid-plan',
            'status' => 'active',
            'features' => [
                [
                    'label' => '   ',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['features.0.label']);

        $dupResponse = $this->postJson('/api/pricing-plans', [
            'name' => 'Duplicate Plan',
            'code' => 'dup-plan',
            'status' => 'active',
            'features' => [
                [
                    'label' => 'Support 24/7',
                    'is_included' => true,
                    'sort_order' => 0,
                ],
                [
                    'label' => '  SUPPORT 24/7  ',
                    'is_included' => false,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $dupResponse->assertStatus(422)
            ->assertJsonValidationErrors(['features.1.label']);
    }

    public function test_merchant_portal_receives_only_active_plans_with_ordered_features(): void
    {
        $activePlan = PricingPlan::create([
            'name' => 'Active Plan',
            'code' => 'active-code',
            'description' => 'Active plan description',
            'status' => PricingPlanStatus::ACTIVE,
            'is_visible_to_merchants' => true,
            'display_order' => 1,
        ]);

        $activePlan->features()->createMany([
            ['label' => 'Feature B', 'is_included' => true, 'sort_order' => 1],
            ['label' => 'Feature A', 'is_included' => false, 'sort_order' => 0],
        ]);

        $inactivePlan = PricingPlan::create([
            'name' => 'Inactive Plan',
            'code' => 'inactive-code',
            'status' => PricingPlanStatus::INACTIVE,
            'is_visible_to_merchants' => true,
            'display_order' => 2,
        ]);

        $inactivePlan->features()->create([
            'label' => 'Inactive Feature',
            'is_included' => true,
            'sort_order' => 0,
        ]);

        $merchant = $this->makeMerchant();
        $merchantUser = $this->makeMerchantPortalUser($merchant);
        \Laravel\Sanctum\Sanctum::actingAs($merchantUser->user);

        $response = $this->getJson('/api/merchant/subscription/plans');

        $response->assertOk()
            ->assertJsonCount(1, 'data.plans')
            ->assertJsonPath('data.plans.0.name', 'Active Plan')
            ->assertJsonPath('data.plans.0.description', 'Active plan description')
            ->assertJsonPath('data.plans.0.features.0.label', 'Feature A')
            ->assertJsonPath('data.plans.0.features.0.is_included', false)
            ->assertJsonPath('data.plans.0.features.1.label', 'Feature B')
            ->assertJsonPath('data.plans.0.features.1.is_included', true);

        $this->assertNotNull($response->json('data.pricing_contact_email'));
    }

    public function test_merchant_cannot_subscribe_to_custom_pricing_plan(): void
    {
        $customPlan = PricingPlan::create([
            'name' => 'Enterprise Custom',
            'code' => 'enterprise-custom',
            'status' => PricingPlanStatus::ACTIVE,
            'is_visible_to_merchants' => true,
            'requires_contact' => true,
            'is_custom_pricing' => true,
        ]);

        $merchant = $this->makeMerchant();
        $merchantUser = $this->makeMerchantPortalUser($merchant);
        \Laravel\Sanctum\Sanctum::actingAs($merchantUser->user);

        $response = $this->postJson('/api/merchant/subscription/subscribe', [
            'pricing_plan_id' => $customPlan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Custom pricing plans require contacting support.');
    }

    public function test_unauthorized_user_cannot_create_or_update_pricing_plans(): void
    {
        $merchant = $this->makeMerchant();
        $merchantUser = $this->makeMerchantPortalUser($merchant);
        \Laravel\Sanctum\Sanctum::actingAs($merchantUser->user);

        $createResponse = $this->postJson('/api/pricing-plans', [
            'name' => 'Hacked Plan',
            'code' => 'hacked-plan',
            'status' => 'active',
        ]);

        $createResponse->assertForbidden();
    }

    public function test_contact_email_setting_configuration_and_fallback(): void
    {
        /** @var SystemSettingService $service */
        $service = app(SystemSettingService::class);

        $email = $service->getPricingContactEmail();
        $this->assertNotEmpty($email);

        $service->set('pricing_contact_email', 'sales-test@fastlogistics.com');
        $this->assertSame('sales-test@fastlogistics.com', $service->getPricingContactEmail());
    }
}
