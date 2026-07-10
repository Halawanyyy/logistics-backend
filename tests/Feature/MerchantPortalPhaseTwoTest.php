<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalPhaseTwoTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_merchant_portal_orders_require_auth_and_active_membership(): void
    {
        $this->getJson('/api/merchant-portal/orders')
            ->assertStatus(401);

        $internalUser = $this->actingAsRole('staff');

        $this->getJson('/api/merchant-portal/orders')
            ->assertStatus(403);

        $this->assertNotNull($internalUser->id);
    }

    public function test_active_merchant_user_can_list_only_own_orders_and_merchant_id_query_is_rejected(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'merchant-b-phase2@example.com']);
        [$governorate, $area] = $this->makeLocation();

        $ownOrder = $this->makeOrder($merchantA, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);
        $this->makeOrder($merchantB, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/orders?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->getJson('/api/merchant-portal/orders?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOrder->id);
    }

    public function test_merchant_user_can_create_manual_order_only_for_own_merchant(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/merchant-portal/orders', [
            'customer_name' => 'Portal Customer',
            'customer_phone' => '01050000000',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Portal Address',
            'payment_type' => 'cod',
            'shipping_fee' => 55,
            'cod_amount' => 250,
            'items' => [[
                'product_name' => 'Portal Item',
                'quantity' => 2,
                'unit_price' => 125,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.customer.name', 'Portal Customer');

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'merchant_id' => $merchant->id,
            'source' => 'manual',
        ]);
    }

    public function test_merchant_user_can_list_active_governorates_and_areas_without_internal_permissions(): void
    {
        $merchant = $this->makeMerchant();
        $activeGovernorate = \App\Modules\LocationPricing\Models\Governorate::create([
            'name' => 'Cairo',
            'is_active' => true,
        ]);
        \App\Modules\LocationPricing\Models\Governorate::create([
            'name' => 'Archived',
            'is_active' => false,
        ]);
        $activeArea = \App\Modules\LocationPricing\Models\Area::create([
            'governorate_id' => $activeGovernorate->id,
            'name' => 'Nasr City',
            'is_active' => true,
        ]);
        \App\Modules\LocationPricing\Models\Area::create([
            'governorate_id' => $activeGovernorate->id,
            'name' => 'Hidden Area',
            'is_active' => false,
        ]);

        $otherGovernorate = \App\Modules\LocationPricing\Models\Governorate::create([
            'name' => 'Giza',
            'is_active' => true,
        ]);
        \App\Modules\LocationPricing\Models\Area::create([
            'governorate_id' => $otherGovernorate->id,
            'name' => 'Dokki',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/locations/governorates')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $activeGovernorate->id)
            ->assertJsonPath('data.0.name', 'Cairo');

        $this->getJson('/api/merchant-portal/locations/areas?governorate_id='.$activeGovernorate->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeArea->id)
            ->assertJsonPath('data.0.name', 'Nasr City');
    }

    public function test_merchant_portal_location_endpoints_require_authentication(): void
    {
        $this->getJson('/api/merchant-portal/locations/governorates')
            ->assertStatus(401);

        $this->getJson('/api/merchant-portal/locations/areas?governorate_id=1')
            ->assertStatus(401);
    }

    public function test_create_payload_rejects_merchant_id_and_source(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-create@example.com']);
        [$governorate, $area] = $this->makeLocation();

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->postJson('/api/merchant-portal/orders', [
            'merchant_id' => $otherMerchant->id,
            'customer_name' => 'Portal Customer',
            'customer_phone' => '01050000001',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Portal Address',
            'payment_type' => 'cod',
            'shipping_fee' => 55,
            'cod_amount' => 250,
            'items' => [[
                'product_name' => 'Portal Item',
                'quantity' => 1,
                'unit_price' => 250,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_id']);

        $this->postJson('/api/merchant-portal/orders', [
            'source' => 'integration',
            'customer_name' => 'Portal Customer',
            'customer_phone' => '01050000001',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Portal Address',
            'payment_type' => 'cod',
            'shipping_fee' => 55,
            'cod_amount' => 250,
            'items' => [[
                'product_name' => 'Portal Item',
                'quantity' => 1,
                'unit_price' => 250,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['source']);
    }

    public function test_merchant_user_can_view_update_confirm_and_cancel_only_own_safe_orders(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::DRAFT,
        ]);
        $otherOrder = $this->makeOrder(
            $this->makeMerchant(['email' => 'other-view@example.com']),
            $governorate,
            $area,
            ['status' => OrderStatus::DRAFT]
        );

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/orders/'.$otherOrder->id)
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->putJson('/api/merchant-portal/orders/'.$order->id, [
            'customer_name' => 'Updated Portal Name',
        ])->assertOk()
            ->assertJsonPath('data.customer.name', 'Updated Portal Name');

        $this->postJson('/api/merchant-portal/orders/'.$order->id.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::CONFIRMED);

        $cancelOrder = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::DRAFT,
        ]);

        $this->postJson('/api/merchant-portal/orders/'.$cancelOrder->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::CANCELLED);
    }

    public function test_locked_or_shipped_orders_cannot_be_updated_or_cancelled(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);
        $shipment = $this->makeShipment($order);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->putJson('/api/merchant-portal/orders/'.$order->id, [
            'customer_name' => 'Blocked Update',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be edited after shipment has been created.');

        $this->postJson('/api/merchant-portal/orders/'.$order->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be cancelled after shipment has been created.');

        $this->assertNotNull($shipment->id);
    }

    public function test_order_list_filters_and_order_label_remain_merchant_scoped(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-filter@example.com']);
        [$governorate, $area] = $this->makeLocation();

        $matchingOrder = $this->makeOrder($merchant, $governorate, $area, [
            'customer_phone' => '01077770000',
            'status' => OrderStatus::CONFIRMED,
        ]);
        $this->makeOrder($otherMerchant, $governorate, $area, [
            'customer_phone' => '01077770000',
            'status' => OrderStatus::CONFIRMED,
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/orders?customer_phone=01077770000&status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingOrder->id);

        $this->get('/api/merchant-portal/orders/'.$matchingOrder->id.'/label?format=html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee($matchingOrder->order_number);
    }

    public function test_shipment_endpoints_are_merchant_scoped_and_read_only(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-shipment@example.com']);
        [$governorate, $area] = $this->makeLocation();

        $ownOrder = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);
        $otherOrder = $this->makeOrder($otherMerchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);

        $ownShipment = $this->makeShipment($ownOrder, [
            'assigned_driver_id' => null,
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
        ]);
        $otherShipment = $this->makeShipment($otherOrder, [
            'status' => ShipmentStatus::IN_TRANSIT,
        ]);

        ShipmentStatusHistory::create([
            'shipment_id' => $ownShipment->id,
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
            'notes' => 'Out for delivery.',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/shipments?status=out_for_delivery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownShipment->id);

        $this->getJson('/api/merchant-portal/shipments/'.$ownShipment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ownShipment->id);

        $this->getJson('/api/merchant-portal/shipments/'.$otherShipment->id)
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/shipments/'.$ownShipment->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $ownShipment->shipment_number)
            ->assertJsonPath('data.tracking_events.0.status', ShipmentStatus::OUT_FOR_DELIVERY);

        $this->get('/api/merchant-portal/shipments/'.$ownShipment->id.'/label?format=zpl')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('^XA');

        $this->get('/api/merchant-portal/shipments/'.$ownShipment->id.'/waybill?format=html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee($ownShipment->shipment_number);

        $this->patchJson('/api/merchant-portal/shipments/'.$ownShipment->id.'/status', [
            'status' => ShipmentStatus::DELIVERED,
        ])->assertStatus(404);

        $this->postJson('/api/merchant-portal/shipments/'.$ownShipment->id.'/assign-driver', [
            'driver_id' => 1,
        ])->assertStatus(404);

        $this->postJson('/api/merchant-portal/shipments/'.$ownShipment->id.'/complete-delivery', [])
            ->assertStatus(404);
    }

    public function test_shipment_filters_remain_merchant_scoped_and_validate_driver_filter_as_forbidden(): void
    {
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-scoped-shipments@example.com']);
        [$governorate, $area] = $this->makeLocation();

        $ownShipment = $this->makeShipment($this->makeOrder($merchant, $governorate, $area), [
            'customer_phone' => '01033333333',
            'cod_amount' => '300.00',
        ]);
        $this->makeShipment($this->makeOrder($otherMerchant, $governorate, $area), [
            'customer_phone' => '01033333333',
            'cod_amount' => '300.00',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/shipments?customer_phone=01033333333&cod_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownShipment->id);

        $this->getJson('/api/merchant-portal/shipments?driver_id=1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('driver_id');
    }

    public function test_capabilities_are_enforced_for_orders_shipments_and_labels(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area);
        $shipment = $this->makeShipment($order);

        $staffUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $staffUser, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($staffUser->fresh());
        $this->getJson('/api/merchant-portal/orders')->assertOk();
        $this->postJson('/api/merchant-portal/orders', [
            'customer_name' => 'Staff Customer',
            'customer_phone' => '01044444444',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Staff Address',
            'payment_type' => 'cod',
            'shipping_fee' => 40,
            'cod_amount' => 100,
            'items' => [[
                'product_name' => 'Staff Item',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertCreated();

        $accountantUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $accountantUser, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($accountantUser->fresh());
        $this->getJson('/api/merchant-portal/orders')->assertOk();
        $this->getJson('/api/merchant-portal/shipments')->assertOk();
        $this->postJson('/api/merchant-portal/orders', [
            'customer_name' => 'Blocked Accountant',
            'customer_phone' => '01055555555',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Blocked Address',
            'payment_type' => 'cod',
            'shipping_fee' => 40,
            'cod_amount' => 100,
            'items' => [[
                'product_name' => 'Blocked Item',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertStatus(403);
        $this->get('/api/merchant-portal/orders/'.$order->id.'/label?format=html')->assertStatus(403);

        $warehouseViewerUser = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $warehouseViewerUser, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($warehouseViewerUser->fresh());
        $this->postJson('/api/merchant-portal/orders', [
            'customer_name' => 'Blocked Warehouse Viewer',
            'customer_phone' => '01066666666',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Blocked Address',
            'payment_type' => 'cod',
            'shipping_fee' => 40,
            'cod_amount' => 100,
            'items' => [[
                'product_name' => 'Blocked Item',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertStatus(403);
        $this->get('/api/merchant-portal/shipments/'.$shipment->id.'/label?format=html')->assertOk();
    }

    public function test_existing_internal_order_and_shipment_apis_still_work(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $this->actingAsSuperAdmin();

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Internal Flow',
            'customer_phone' => '01088888888',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Internal Address',
            'payment_type' => 'cod',
            'shipping_fee' => 70,
            'cod_amount' => 320,
            'items' => [[
                'product_name' => 'Internal Item',
                'quantity' => 1,
                'unit_price' => 320,
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('data.id');

        $this->postJson('/api/orders/'.$orderId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::CONFIRMED);

        $this->postJson('/api/orders/'.$orderId.'/shipments')
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId);
    }
}
