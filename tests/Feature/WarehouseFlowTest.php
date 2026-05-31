<?php

namespace Tests\Feature;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\WarehouseReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class WarehouseFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_warehouse_stock_flow_and_sellable_return_restore_stock(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productResponse = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Phone Case',
            'sku' => 'CASE-001',
        ]);

        $warehouseProductId = $productResponse->json('data.id');
        $productResponse->assertCreated();

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProductId,
            'type' => 'in',
            'quantity' => 5,
            'notes' => 'Initial stock in',
        ])->assertCreated();

        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'fulfillment_type' => OrderFulfillmentType::FROM_WAREHOUSE,
            'cod_amount' => '150.00',
            'shipping_fee' => '25.00',
        ], [[
            'product_name' => 'Phone Case',
            'quantity' => 1,
            'unit_price' => '150.00',
        ]]);

        $orderItemId = $order->items->first()->id;

        $this->postJson('/api/orders/'.$order->id.'/reserve-stock', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'order_item_id' => $orderItemId,
                    'warehouse_product_id' => $warehouseProductId,
                    'quantity' => 1,
                ],
            ],
        ])->assertCreated();

        $this->postJson('/api/orders/'.$order->id.'/fulfill-from-warehouse')
            ->assertOk();

        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data.id');

        $deliveryResponse = $this->postJson('/api/shipments/'.$shipmentId.'/complete-delivery', [
            'shipment_status' => ShipmentStatus::FAILED,
            'notes' => 'Customer refused item',
            'items' => [
                [
                    'order_item_id' => $orderItemId,
                    'warehouse_product_id' => $warehouseProductId,
                    'result_status' => 'returned',
                    'quantity' => 1,
                    'return_condition' => 'sellable',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::FAILED);

        $warehouseReturn = WarehouseReturn::query()->firstOrFail();
        $warehouseReturnItemId = $warehouseReturn->items()->value('id');

        $this->assertSame('pending', $warehouseReturn->status);

        $this->postJson('/api/warehouse-returns/'.$warehouseReturn->id.'/receive', [
            'items' => [[
                'warehouse_return_item_id' => $warehouseReturnItemId,
                'received_quantity' => 1,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->postJson('/api/warehouse-returns/'.$warehouseReturn->id.'/inspect', [
            'items' => [[
                'warehouse_return_item_id' => $warehouseReturnItemId,
                'condition' => 'sellable',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'inspected');

        $this->postJson('/api/warehouse-returns/'.$warehouseReturn->id.'/restock', [
            'items' => [[
                'warehouse_return_item_id' => $warehouseReturnItemId,
                'quantity' => 1,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'restocked');

        $stock = InventoryStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('warehouse_product_id', $warehouseProductId)
            ->firstOrFail();

        $this->assertSame(5, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved);
        $this->assertDatabaseCount('warehouse_returns', 1);
        $this->assertInstanceOf(WarehouseReturn::class, WarehouseReturn::query()->first());
    }
}
