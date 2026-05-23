<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Enums\DriverCashClosureStatus;
use App\Modules\Finance\Models\DriverCashClosure;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class BackendPolishPhaseTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_order_show_includes_shipment_summary_and_hides_detailed_stock_reservations(): void
    {
        [$merchant, $governorate, $area] = $this->createLocationContext();
        $order = $this->createOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::SHIPMENT_CREATED,
            'fulfillment_type' => OrderFulfillmentType::PICKUP_FROM_MERCHANT,
        ]);
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'shipment_number' => 'SHP-000001',
            'merchant_id' => $merchant->id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => $order->delivery_address,
            'cod_amount' => '125.50',
            'shipping_fee' => '30.00',
            'status' => ShipmentStatus::PENDING_PICKUP,
        ]);

        $response = $this->getJson('/api/orders/'.$order->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.fulfillment_type', OrderFulfillmentType::PICKUP_FROM_MERCHANT)
            ->assertJsonPath('data.pickup_type', OrderFulfillmentType::PICKUP_FROM_MERCHANT)
            ->assertJsonPath('data.shipment.shipment_id', $shipment->id)
            ->assertJsonPath('data.shipment.shipment_number', 'SHP-000001')
            ->assertJsonPath('data.shipment.shipment_status', ShipmentStatus::PENDING_PICKUP)
            ->assertJsonPath('data.has_stock_reservations', false)
            ->assertJsonPath('data.stock_reservation_status', null)
            ->assertJsonMissingPath('data.stock_reservations');
    }

    public function test_shipment_creation_updates_order_status_and_locks_order_changes(): void
    {
        [$merchant, $governorate, $area] = $this->createLocationContext();
        $order = $this->createOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);

        $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::SHIPMENT_CREATED,
        ]);

        $this->putJson('/api/orders/'.$order->id, [
            'customer_name' => 'Updated Name',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be edited after shipment has been created.');

        $this->deleteJson('/api/orders/'.$order->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be deleted after shipment has been created.');
    }

    public function test_orders_index_supports_frontend_filters(): void
    {
        [$merchantA, $governorate, $area] = $this->createLocationContext('Alex', 'Smouha', 'Merchant Alpha');
        $merchantB = Merchant::create([
            'name' => 'Merchant Beta',
            'company_name' => 'Merchant Beta Co',
            'phone' => '01000000002',
            'email' => 'beta@example.com',
            'status' => 'active',
        ]);

        $matchingOrder = $this->createOrder($merchantA, $governorate, $area, [
            'order_number' => 'ORD-SEARCH-1',
            'customer_name' => 'Alice',
            'customer_phone' => '01234567890',
            'status' => OrderStatus::CONFIRMED,
            'payment_type' => PaymentType::COD,
            'fulfillment_type' => OrderFulfillmentType::FROM_WAREHOUSE,
        ]);

        $otherOrder = $this->createOrder($merchantB, $governorate, $area, [
            'order_number' => 'ORD-OTHER-1',
            'customer_name' => 'Bob',
            'customer_phone' => '01999999999',
            'status' => OrderStatus::DRAFT,
            'payment_type' => PaymentType::PREPAID,
            'fulfillment_type' => OrderFulfillmentType::PICKUP_FROM_MERCHANT,
        ]);

        $response = $this->getJson('/api/orders?merchant_name=Alpha&status=confirmed&fulfillment_type=from_warehouse&payment_type=cod&search=SEARCH');

        $response->assertOk();
        $this->assertSame([$matchingOrder->id], array_column($response->json('data'), 'id'));
        $response->assertJsonMissing(['id' => $otherOrder->id]);
    }

    public function test_areas_can_be_filtered_by_governorate(): void
    {
        $governorateA = Governorate::create(['name' => 'Cairo']);
        $governorateB = Governorate::create(['name' => 'Giza']);
        $matchingArea = Area::create(['governorate_id' => $governorateA->id, 'name' => 'Nasr City']);
        Area::create(['governorate_id' => $governorateB->id, 'name' => 'Dokki']);

        $response = $this->getJson('/api/areas?governorate_id='.$governorateA->id);

        $response->assertOk();
        $this->assertSame([$matchingArea->id], array_column($response->json('data'), 'id'));
    }

    public function test_shipments_print_list_returns_filtered_summary_payload(): void
    {
        [$merchant, $governorate, $area] = $this->createLocationContext();
        $driver = Driver::create([
            'name' => 'Driver One',
            'phone' => '01111111111',
            'status' => 'active',
        ]);

        $order = $this->createOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::SHIPMENT_CREATED,
        ]);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'shipment_number' => 'SHP-PRINT-1',
            'merchant_id' => $merchant->id,
            'customer_name' => 'Print Customer',
            'customer_phone' => '01010101010',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Print Address',
            'cod_amount' => '200.00',
            'shipping_fee' => '40.00',
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
            'assigned_driver_id' => $driver->id,
            'created_at' => '2026-04-25 10:00:00',
            'updated_at' => '2026-04-25 10:00:00',
        ]);

        Shipment::query()->whereKey($shipment->id)->update([
            'created_at' => '2026-04-25 10:00:00',
            'updated_at' => '2026-04-25 10:00:00',
        ]);

        $response = $this->getJson('/api/shipments/print-list?assigned_driver_id='.$driver->id.'&date=2026-04-25&delivery_governorate_id='.$governorate->id.'&delivery_area_id='.$area->id.'&status=out_for_delivery');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.total_shipments', 1)
            ->assertJsonPath('data.summary.total_cod_amount', '200.00')
            ->assertJsonPath('data.summary.total_shipping_fee', '40.00')
            ->assertJsonPath('data.shipments.0.shipment_number', 'SHP-PRINT-1')
            ->assertJsonPath('data.shipments.0.driver_name', 'Driver One')
            ->assertJsonPath('data.shipments.0.merchant_phone', $merchant->phone);
    }

    public function test_driver_cash_closure_generate_calculates_expected_amount_and_verification_fields(): void
    {
        $user = $this->actingAsSuperAdmin();

        [$merchant, $governorate, $area] = $this->createLocationContext();
        $driver = Driver::create([
            'name' => 'Cash Driver',
            'phone' => '01212121212',
            'status' => 'active',
        ]);

        $codOrder = $this->createOrder($merchant, $governorate, $area, [
            'order_number' => 'ORD-COD-1',
            'status' => OrderStatus::SHIPMENT_CREATED,
            'payment_type' => PaymentType::COD,
            'cod_amount' => '300.00',
        ]);

        $prepaidOrder = $this->createOrder($merchant, $governorate, $area, [
            'order_number' => 'ORD-PREPAID-1',
            'status' => OrderStatus::SHIPMENT_CREATED,
            'payment_type' => PaymentType::PREPAID,
            'cod_amount' => '500.00',
        ]);

        $codShipment = Shipment::create([
            'order_id' => $codOrder->id,
            'shipment_number' => 'SHP-COD-1',
            'merchant_id' => $merchant->id,
            'customer_name' => $codOrder->customer_name,
            'customer_phone' => $codOrder->customer_phone,
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => $codOrder->delivery_address,
            'cod_amount' => '300.00',
            'shipping_fee' => '30.00',
            'status' => ShipmentStatus::DELIVERED,
            'assigned_driver_id' => $driver->id,
        ]);

        Shipment::create([
            'order_id' => $prepaidOrder->id,
            'shipment_number' => 'SHP-PREPAID-1',
            'merchant_id' => $merchant->id,
            'customer_name' => $prepaidOrder->customer_name,
            'customer_phone' => $prepaidOrder->customer_phone,
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => $prepaidOrder->delivery_address,
            'cod_amount' => '500.00',
            'shipping_fee' => '30.00',
            'status' => ShipmentStatus::DELIVERED,
            'assigned_driver_id' => $driver->id,
        ]);

        Shipment::query()->whereKey($codShipment->id)->update([
            'updated_at' => '2026-04-25 12:00:00',
        ]);

        Shipment::query()->where('shipment_number', 'SHP-PREPAID-1')->update([
            'updated_at' => '2026-04-25 12:00:00',
        ]);

        $response = $this->postJson('/api/drivers/'.$driver->id.'/cash-closures/generate', [
            'date' => '2026-04-25',
            'received_amount' => 500,
            'status' => DriverCashClosureStatus::VERIFIED,
            'notes' => 'Daily closure',
            'expected_amount' => 9999,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.date', '2026-04-25')
            ->assertJsonPath('data.expected_amount', '300.00')
            ->assertJsonPath('data.received_amount', '500.00')
            ->assertJsonPath('data.difference_amount', '200.00')
            ->assertJsonPath('data.status', DriverCashClosureStatus::VERIFIED)
            ->assertJsonPath('data.verified_by', $user->id);

        $this->assertDatabaseHas('driver_cash_closures', [
            'driver_id' => $driver->id,
        ]);

        $closure = DriverCashClosure::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-04-25', $closure->closure_date?->toDateString());
        $this->assertSame('300.00', $closure->expected_amount);
        $this->assertSame('200.00', $closure->difference_amount);
    }

    /**
     * @return array{0: Merchant, 1: Governorate, 2: Area}
     */
    private function createLocationContext(
        string $governorateName = 'Cairo',
        string $areaName = 'Maadi',
        string $merchantName = 'Merchant One',
    ): array {
        $merchant = Merchant::create([
            'name' => $merchantName,
            'company_name' => $merchantName.' Co',
            'phone' => '01000000001',
            'email' => strtolower(str_replace(' ', '.', $merchantName)).'@example.com',
            'status' => 'active',
        ]);

        $governorate = Governorate::create([
            'name' => $governorateName,
        ]);

        $area = Area::create([
            'governorate_id' => $governorate->id,
            'name' => $areaName,
        ]);

        return [$merchant, $governorate, $area];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createOrder(Merchant $merchant, Governorate $governorate, Area $area, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'merchant_id' => $merchant->id,
            'order_number' => 'ORD-'.str_pad((string) (Order::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'customer_name' => 'Test Customer',
            'customer_phone' => '01012345678',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Test Address',
            'cod_amount' => '125.50',
            'shipping_fee' => '30.00',
            'payment_type' => PaymentType::COD,
            'fulfillment_type' => OrderFulfillmentType::PICKUP_FROM_MERCHANT,
            'source' => 'manual',
            'status' => OrderStatus::CONFIRMED,
            'requires_review' => false,
        ], $overrides));

        $order->items()->create([
            'product_name' => 'Box',
            'quantity' => 1,
            'unit_price' => '125.50',
        ]);

        return $order;
    }
}
