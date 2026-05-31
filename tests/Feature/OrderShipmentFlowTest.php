<?php

namespace Tests\Feature;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class OrderShipmentFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_create_confirm_ship_and_lock_order_after_shipment(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Ahmed Adel',
            'customer_phone' => '01000000000',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Nasr City',
            'payment_type' => 'cod',
            'shipping_fee' => 80,
            'cod_amount' => 300,
            'items' => [
                [
                    'product_name' => 'Phone Case',
                    'quantity' => 1,
                    'unit_price' => 300,
                ],
            ],
        ]);

        $orderId = $orderResponse->json('data.id');

        $orderResponse
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::DRAFT);

        $this->postJson('/api/orders/'.$orderId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::CONFIRMED);

        $shipmentResponse = $this->postJson('/api/orders/'.$orderId.'/shipments');
        $shipmentId = $shipmentResponse->json('data.id');

        $shipmentResponse
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.status', ShipmentStatus::PENDING_PICKUP);

        $this->patchJson('/api/shipments/'.$shipmentId.'/status', [
            'status' => ShipmentStatus::IN_TRANSIT,
            'notes' => 'Shipment left the hub.',
        ])->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT);

        $this->putJson('/api/orders/'.$orderId, [
            'customer_name' => 'Updated Name',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be edited after shipment has been created.');

        $this->deleteJson('/api/orders/'.$orderId)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be deleted after shipment has been created.');
    }

    public function test_warehouse_order_cannot_create_shipment_before_fulfillment_and_cannot_be_edited_after_reservation(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Warehouse Box',
            'sku' => 'WH-BOX-001',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'type' => 'in',
            'quantity' => 2,
            'notes' => 'Warehouse flow seed',
        ])->assertCreated();

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Warehouse Customer',
            'customer_phone' => '01099999999',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Warehouse Lane',
            'payment_type' => 'cod',
            'shipping_fee' => 60,
            'cod_amount' => 250,
            'fulfillment_type' => OrderFulfillmentType::FROM_WAREHOUSE,
            'status' => OrderStatus::CONFIRMED,
            'items' => [[
                'product_name' => 'Warehouse Box',
                'quantity' => 1,
                'unit_price' => 250,
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('data.id');
        $orderItemId = $orderResponse->json('data.items.0.id');

        $this->postJson('/api/orders/'.$orderId.'/shipments')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Warehouse fulfillment orders can create shipments only after warehouse fulfillment is completed.');

        $this->postJson('/api/orders/'.$orderId.'/reserve-stock', [
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'order_item_id' => $orderItemId,
                'warehouse_product_id' => $productId,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->putJson('/api/orders/'.$orderId, [
            'customer_name' => 'Changed After Reservation',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Order cannot be edited after warehouse processing has started.');

        $this->postJson('/api/orders/'.$orderId.'/fulfill-from-warehouse')
            ->assertOk();

        $this->postJson('/api/orders/'.$orderId.'/shipments')
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderId);
    }

    public function test_status_patch_blocks_delivery_completion_statuses(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'shipping_fee' => '25.00',
            'cod_amount' => '150.00',
        ], [[
            'product_name' => 'Patch Test',
            'quantity' => 1,
            'unit_price' => '150.00',
        ]]);

        $shipmentResponse = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk();
        $shipmentId = $shipmentResponse->json('data.id');

        $this->patchJson('/api/shipments/'.$shipmentId.'/status', [
            'status' => ShipmentStatus::DELIVERED,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_order_shipment_and_print_list_filters_accept_new_frontend_params(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $driver = $this->makeDriver();

        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'payment_type' => 'cod',
            'cod_amount' => '180.00',
            'shipping_fee' => '35.00',
            'customer_phone' => '01012345678',
        ], [[
            'product_name' => 'Filter Item',
            'quantity' => 1,
            'unit_price' => '180.00',
        ]]);

        $shipmentResponse = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk();
        $shipmentId = $shipmentResponse->json('data.id');
        $shipmentNumber = $shipmentResponse->json('data.shipment_number');

        $this->postJson('/api/shipments/'.$shipmentId.'/assign-driver', [
            'driver_id' => $driver->id,
        ])->assertOk();

        $this->getJson('/api/orders?has_shipment=1&customer_phone=01012345678&order_number='.$order->order_number)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/shipments?driver_id='.$driver->id.'&payment_type=cod&cod_only=1&shipment_number='.$shipmentNumber)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/shipments/print-list?date='.now()->toDateString().'&merchant_id='.$merchant->id.'&assigned_driver_id='.$driver->id)
            ->assertOk()
            ->assertJsonPath('data.summary.total_shipments', 1);
    }
}
