<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\LocationPricing\Models\PricingPlan;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Models\PickPackFeeRule;
use App\Modules\Warehouse\Models\StorageFeeRule;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class CompanyCatalogAndOrderPackagingTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_admin_can_create_packaging_catalog_item(): void
    {
        $response = $this->postJson('/api/company-catalog-items', [
            'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
            'category' => 'flyer',
            'name' => 'Flyer Medium',
            'sku' => 'FLYER-M',
            'description' => 'Medium flyer bag for small orders.',
            'unit_price' => 3.5,
            'currency' => 'EGP',
            'size_label' => 'Medium',
            'length' => 30,
            'width' => 20,
            'unit' => 'cm',
            'is_visible_to_merchants' => true,
            'sort_order' => 10,
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', CompanyCatalogItem::TYPE_PACKAGING_PRODUCT)
            ->assertJsonPath('data.sku', 'FLYER-M')
            ->assertJsonPath('data.unit_price', '3.50');

        $this->assertDatabaseHas('company_catalog_items', [
            'sku' => 'FLYER-M',
            'name' => 'Flyer Medium',
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ]);
    }

    public function test_inactive_catalog_item_does_not_appear_in_order_dropdown_or_shop(): void
    {
        $activeItem = $this->createCatalogItem([
            'name' => 'Active Flyer',
            'sku' => 'PKG-ACTIVE',
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ]);
        $inactiveItem = $this->createCatalogItem([
            'name' => 'Inactive Flyer',
            'sku' => 'PKG-INACTIVE',
            'status' => CompanyCatalogItem::STATUS_INACTIVE,
        ]);

        $dropdownResponse = $this->getJson('/api/company-catalog-items?type=packaging_product&status=active')
            ->assertOk();

        $dropdownIds = array_column($dropdownResponse->json('data'), 'id');

        $this->assertContains($activeItem->id, $dropdownIds);
        $this->assertNotContains($inactiveItem->id, $dropdownIds);

        $shopResponse = $this->getJson('/api/merchant-shop/catalog')
            ->assertOk();

        $shopIds = array_column($shopResponse->json('data'), 'id');

        $this->assertContains($activeItem->id, $shopIds);
        $this->assertNotContains($inactiveItem->id, $shopIds);
    }

    public function test_order_can_be_created_with_packaging_items_and_stores_snapshot_values(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $catalogItem = $this->createCatalogItem([
            'name' => 'Bubble Wrap Roll',
            'sku' => 'PKG-BWRAP',
            'unit_price' => '9.50',
            'size_label' => 'Roll',
            'category' => 'bubble_wrap',
        ]);

        $response = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Packaging Customer',
            'customer_phone' => '01012345678',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Nasr City',
            'payment_type' => 'cod',
            'shipping_fee' => 45,
            'cod_amount' => 300,
            'requires_packaging' => true,
            'package_notes' => 'Use extra bubble wrap',
            'packaging_items' => [[
                'company_catalog_item_id' => $catalogItem->id,
                'quantity' => 2,
                'notes' => 'Fragile products',
            ]],
            'items' => [[
                'product_name' => 'Glass Mug',
                'quantity' => 1,
                'unit_price' => 300,
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.requires_packaging', true)
            ->assertJsonPath('data.total_packaging_fee', '19.00')
            ->assertJsonPath('data.packaging_items.0.item_name', 'Bubble Wrap Roll')
            ->assertJsonPath('data.packaging_items.0.item_sku', 'PKG-BWRAP')
            ->assertJsonPath('data.packaging_items.0.unit_price', '9.50')
            ->assertJsonPath('data.packaging_items.0.total_price', '19.00');

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('order_packaging_items', [
            'order_id' => $orderId,
            'company_catalog_item_id' => $catalogItem->id,
            'item_name' => 'Bubble Wrap Roll',
            'item_sku' => 'PKG-BWRAP',
            'unit_price' => '9.50',
            'quantity' => 2,
            'total_price' => '19.00',
        ]);
    }

    public function test_updating_catalog_price_does_not_change_old_order_snapshot_and_order_update_recalculates_total_packaging_fee(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $catalogItem = $this->createCatalogItem([
            'name' => 'Carton Medium',
            'sku' => 'PKG-CARTON-M',
            'unit_price' => '15.00',
            'size_label' => 'Medium',
        ]);

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Snapshot Customer',
            'customer_phone' => '01000000001',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Maadi',
            'payment_type' => 'cod',
            'shipping_fee' => 35,
            'cod_amount' => 200,
            'packaging_items' => [[
                'company_catalog_item_id' => $catalogItem->id,
                'quantity' => 1,
            ]],
            'items' => [[
                'product_name' => 'Coffee Machine',
                'quantity' => 1,
                'unit_price' => 200,
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('data.id');

        $this->putJson('/api/company-catalog-items/'.$catalogItem->id, [
            'unit_price' => 25,
        ])->assertOk();

        $this->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.packaging_items.0.unit_price', '15.00')
            ->assertJsonPath('data.total_packaging_fee', '15.00');

        $this->putJson('/api/orders/'.$orderId, [
            'requires_packaging' => true,
            'packaging_items' => [[
                'company_catalog_item_id' => $catalogItem->id,
                'quantity' => 3,
                'notes' => 'Repriced future selection',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.total_packaging_fee', '75.00')
            ->assertJsonPath('data.packaging_items.0.unit_price', '25.00')
            ->assertJsonPath('data.packaging_items.0.quantity', 3);
    }

    public function test_merchant_shop_returns_read_only_visible_catalog_items_and_preview_includes_storage_and_pick_pack_rules(): void
    {
        $merchant = $this->createMerchantWithPricingPlan();
        $visiblePackaging = $this->createCatalogItem([
            'name' => 'Sticker Pack',
            'sku' => 'PKG-STICKER',
            'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
            'is_visible_to_merchants' => true,
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ]);
        $visibleService = $this->createCatalogItem([
            'name' => 'Gift Wrapping Service',
            'sku' => 'SRV-GIFT-WRAP',
            'type' => CompanyCatalogItem::TYPE_OTHER_SERVICE,
            'category' => 'gift',
            'is_visible_to_merchants' => true,
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ]);
        $hiddenItem = $this->createCatalogItem([
            'name' => 'Hidden Product',
            'sku' => 'PKG-HIDDEN',
            'is_visible_to_merchants' => false,
        ]);

        StorageFeeRule::create([
            'merchant_id' => $merchant->id,
            'pricing_plan_id' => $merchant->pricing_plan_id,
            'name' => 'Merchant Storage Rule',
            'calculation_type' => 'per_item_per_month',
            'free_units' => 10,
            'unit_price' => '2.50',
            'status' => 'active',
        ]);

        PickPackFeeRule::create([
            'merchant_id' => $merchant->id,
            'pricing_plan_id' => $merchant->pricing_plan_id,
            'name' => 'Merchant Pick Pack Rule',
            'pick_fee_first_item' => '6.00',
            'pick_fee_additional_item' => '2.00',
            'packing_fee_base' => '4.00',
            'fragile_extra_fee' => '3.00',
            'status' => 'active',
        ]);

        $catalogResponse = $this->getJson('/api/merchant-shop/catalog')
            ->assertOk();

        $catalogIds = array_column($catalogResponse->json('data'), 'id');

        $this->assertContains($visiblePackaging->id, $catalogIds);
        $this->assertContains($visibleService->id, $catalogIds);
        $this->assertNotContains($hiddenItem->id, $catalogIds);
        $this->assertArrayNotHasKey('cart', $catalogResponse->json());
        $this->assertArrayNotHasKey('checkout', $catalogResponse->json());

        $this->getJson('/api/merchants/'.$merchant->id.'/shop-preview')
            ->assertOk()
            ->assertJsonPath('data.packaging_products.0.id', $visiblePackaging->id)
            ->assertJsonPath('data.services.0.id', $visibleService->id)
            ->assertJsonPath('data.storage_fees.0.name', 'Merchant Storage Rule')
            ->assertJsonPath('data.pick_pack_fees.0.name', 'Merchant Pick Pack Rule');
    }

    public function test_user_without_permission_cannot_manage_catalog(): void
    {
        $this->actingAsRole('driver');

        $this->postJson('/api/company-catalog-items', [
            'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
            'name' => 'Forbidden Catalog Item',
        ])->assertForbidden();
    }

    public function test_no_duplicate_packaging_charge_is_created_when_auto_charge_is_enabled(): void
    {
        app(SystemSettingService::class)->set('packaging_fee_auto_charge', true, auth()->user());

        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $catalogItem = $this->createCatalogItem([
            'name' => 'Mailer Bag',
            'sku' => 'PKG-MAILER',
            'unit_price' => '5.00',
        ]);

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Charge Customer',
            'customer_phone' => '01077777777',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Heliopolis',
            'payment_type' => 'cod',
            'shipping_fee' => 50,
            'cod_amount' => 180,
            'packaging_items' => [[
                'company_catalog_item_id' => $catalogItem->id,
                'quantity' => 2,
            ]],
            'items' => [[
                'product_name' => 'Shoes',
                'quantity' => 1,
                'unit_price' => 180,
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('data.id');

        $this->postJson('/api/orders/'.$orderId.'/confirm')
            ->assertOk();

        $this->assertDatabaseCount('warehouse_charges', 1);
        $this->assertDatabaseHas('warehouse_charges', [
            'order_id' => $orderId,
            'type' => 'packaging_product',
            'amount' => '10.00',
        ]);

        $this->putJson('/api/orders/'.$orderId, [
            'requires_packaging' => true,
            'packaging_items' => [[
                'company_catalog_item_id' => $catalogItem->id,
                'quantity' => 3,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.total_packaging_fee', '15.00');

        $this->assertEquals(1, WarehouseCharge::query()->count());
        $this->assertDatabaseHas('warehouse_charges', [
            'order_id' => $orderId,
            'type' => 'packaging_product',
            'amount' => '15.00',
            'status' => 'pending',
        ]);
    }

    private function createCatalogItem(array $overrides = []): CompanyCatalogItem
    {
        $sequence = CompanyCatalogItem::query()->count() + 1;

        return CompanyCatalogItem::create(array_merge([
            'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
            'category' => 'flyer',
            'name' => 'Catalog Item '.$sequence,
            'sku' => 'CAT-'.$sequence,
            'description' => 'Catalog item '.$sequence,
            'unit_price' => '5.00',
            'currency' => 'EGP',
            'size_label' => 'Standard',
            'is_visible_to_merchants' => true,
            'sort_order' => $sequence,
            'status' => CompanyCatalogItem::STATUS_ACTIVE,
        ], $overrides));
    }

    private function createMerchantWithPricingPlan(): Merchant
    {
        $plan = PricingPlan::create([
            'name' => 'Gold Plan',
            'code' => 'GOLD-PLAN',
            'status' => 'active',
        ]);

        return $this->makeMerchant([
            'pricing_plan_id' => $plan->id,
        ]);
    }
}
