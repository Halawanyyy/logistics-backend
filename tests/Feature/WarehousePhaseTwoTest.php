<?php

namespace Tests\Feature;

use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class WarehousePhaseTwoTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_receipt_confirmation_increases_stock_and_can_be_filtered(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Bulk Socks',
            'sku' => 'SOCK-001',
        ])->assertCreated()->json('data.id');

        $receiptResponse = $this->postJson('/api/warehouse-receipts', [
            'merchant_id' => $merchant->id,
            'warehouse_id' => $warehouse->id,
            'notes' => 'First inbound receipt',
            'items' => [[
                'warehouse_product_id' => $productId,
                'quantity' => 5,
                'accepted_quantity' => 4,
                'rejected_quantity' => 1,
                'condition' => 'good',
            ]],
        ])->assertCreated();

        $receiptId = $receiptResponse->json('data.id');

        $this->postJson('/api/warehouse-receipts/'.$receiptId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('inventory_stocks', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'quantity_available' => 4,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'type' => StockMovementType::STOCK_IN,
            'reference_type' => 'warehouse_receipt',
        ]);

        $this->getJson('/api/warehouse-receipts?status=confirmed&merchant_id='.$merchant->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_barcode_scan_and_low_stock_endpoints_work(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productResponse = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Shirt',
            'sku' => 'SHIRT-001',
            'minimum_stock_quantity' => 5,
            'low_stock_alert_enabled' => true,
        ])->assertCreated();

        $productId = $productResponse->json('data.id');
        $barcode = $productResponse->json('data.barcode');

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'type' => 'in',
            'quantity' => 3,
            'notes' => 'Low stock seed',
        ])->assertCreated();

        $this->postJson('/api/warehouse/barcode/scan', [
            'barcode' => $barcode,
        ])->assertOk()
            ->assertJsonPath('data.warehouse_product.id', $productId)
            ->assertJsonPath('data.stock_summary.total_available_quantity', 3);

        $this->getJson('/api/inventory-stocks/low-stock?sku=SHIRT-001')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/warehouse-products/low-stock?barcode='.$barcode)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_fulfillment_aliases_and_pick_pack_workflow_stay_backward_compatible(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Warehouse Customer',
            'customer_phone' => '01000011111',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Warehouse Fulfillment Address',
            'payment_type' => 'cod',
            'shipping_fee' => 80,
            'cod_amount' => 300,
            'fulfillment_type' => 'warehouse_fulfillment',
            'status' => OrderStatus::CONFIRMED,
            'items' => [[
                'product_name' => 'Box',
                'quantity' => 1,
                'unit_price' => 300,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.fulfillment_type', OrderFulfillmentType::FROM_WAREHOUSE)
            ->assertJsonPath('data.warehouse_fulfillment_status', 'pending_reservation');

        $orderId = $orderResponse->json('data.id');
        $orderItemId = $orderResponse->json('data.items.0.id');

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Box',
            'sku' => 'BOX-001',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'type' => 'in',
            'quantity' => 2,
            'notes' => 'Workflow stock seed',
        ])->assertCreated();

        $this->postJson('/api/orders/'.$orderId.'/reserve-stock', [
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'order_item_id' => $orderItemId,
                'warehouse_product_id' => $productId,
                'quantity' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'reserved');

        $this->postJson('/api/orders/'.$orderId.'/warehouse/start-picking', [])
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'picking');

        $this->postJson('/api/orders/'.$orderId.'/warehouse/mark-picked')
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'picked');

        $this->postJson('/api/orders/'.$orderId.'/warehouse/start-packing')
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'packing');

        $this->postJson('/api/orders/'.$orderId.'/warehouse/mark-packed', [])
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'packed');

        $this->postJson('/api/orders/'.$orderId.'/warehouse/ready-for-dispatch')
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'ready_for_dispatch');

        $this->postJson('/api/orders/'.$orderId.'/fulfill-from-warehouse')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'fulfilled');

        $this->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.warehouse_fulfillment_status', 'fulfilled');

        $stock = InventoryStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('warehouse_product_id', $productId)
            ->firstOrFail();

        $this->assertSame(1, $stock->quantity_available);
        $this->assertSame(0, $stock->quantity_reserved);
    }

    public function test_merchant_pickup_orders_cannot_reserve_stock(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Pickup Item',
            'sku' => 'PICKUP-001',
        ])->assertCreated()->json('data.id');

        $orderResponse = $this->postJson('/api/orders', [
            'merchant_id' => $merchant->id,
            'customer_name' => 'Pickup Customer',
            'customer_phone' => '01000022222',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Pickup Address',
            'payment_type' => 'cod',
            'shipping_fee' => 50,
            'cod_amount' => 200,
            'fulfillment_type' => 'merchant_pickup',
            'status' => OrderStatus::CONFIRMED,
            'items' => [[
                'product_name' => 'Pickup Item',
                'quantity' => 1,
                'unit_price' => 200,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.fulfillment_type', OrderFulfillmentType::PICKUP_FROM_MERCHANT);

        $orderId = $orderResponse->json('data.id');
        $orderItemId = $orderResponse->json('data.items.0.id');

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'type' => 'in',
            'quantity' => 1,
            'notes' => 'Pickup seed',
        ])->assertCreated();

        $this->postJson('/api/orders/'.$orderId.'/reserve-stock', [
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'order_item_id' => $orderItemId,
                'warehouse_product_id' => $productId,
                'quantity' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Only warehouse fulfillment orders can reserve stock.');
    }

    public function test_manual_return_lifecycle_can_restock_and_close(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Returned Lamp',
            'sku' => 'RETURN-001',
        ])->assertCreated()->json('data.id');

        $returnResponse = $this->postJson('/api/warehouse-returns', [
            'warehouse_id' => $warehouse->id,
            'notes' => 'Manual return batch',
            'items' => [[
                'warehouse_product_id' => $productId,
                'expected_quantity' => 2,
                'condition' => 'sellable',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $returnId = $returnResponse->json('data.id');
        $returnItemId = $returnResponse->json('data.items.0.id');

        $this->postJson('/api/warehouse-returns/'.$returnId.'/receive', [
            'items' => [[
                'warehouse_return_item_id' => $returnItemId,
                'received_quantity' => 2,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->postJson('/api/warehouse-returns/'.$returnId.'/inspect', [
            'items' => [[
                'warehouse_return_item_id' => $returnItemId,
                'condition' => 'sellable',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'inspected');

        $this->postJson('/api/warehouse-returns/'.$returnId.'/restock', [
            'items' => [[
                'warehouse_return_item_id' => $returnItemId,
                'quantity' => 2,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', 'restocked');

        $this->postJson('/api/warehouse-returns/'.$returnId.'/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('inventory_stocks', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $productId,
            'quantity_available' => 2,
        ]);

        $this->getJson('/api/warehouse-returns?status=closed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_transfer_dispatch_and_receive_move_stock_between_warehouses(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $sourceWarehouse = $this->makeWarehouse($governorate, $area);
        [$governorateTwo, $areaTwo] = $this->makeLocation('Giza', 'Dokki');
        $destinationWarehouse = $this->makeWarehouse($governorateTwo, $areaTwo);

        $productId = $this->postJson('/api/warehouse-products', [
            'merchant_id' => $merchant->id,
            'name' => 'Transfer Item',
            'sku' => 'TRN-001',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/inventory-stocks/adjust', [
            'warehouse_id' => $sourceWarehouse->id,
            'warehouse_product_id' => $productId,
            'type' => 'in',
            'quantity' => 5,
            'notes' => 'Transfer seed',
        ])->assertCreated();

        $transferResponse = $this->postJson('/api/warehouse-transfers', [
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'notes' => 'Move transfer item',
            'items' => [[
                'warehouse_product_id' => $productId,
                'quantity' => 3,
            ]],
        ])->assertCreated();

        $transferId = $transferResponse->json('data.id');

        $this->postJson('/api/warehouse-transfers/'.$transferId.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson('/api/warehouse-transfers/'.$transferId.'/dispatch')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->postJson('/api/warehouse-transfers/'.$transferId.'/receive', [])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $sourceStock = InventoryStock::query()
            ->where('warehouse_id', $sourceWarehouse->id)
            ->where('warehouse_product_id', $productId)
            ->firstOrFail();
        $destinationStock = InventoryStock::query()
            ->where('warehouse_id', $destinationWarehouse->id)
            ->where('warehouse_product_id', $productId)
            ->firstOrFail();

        $this->assertSame(2, $sourceStock->quantity_available);
        $this->assertSame(3, $destinationStock->quantity_available);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::STOCK_TRANSFER_OUT]);
        $this->assertDatabaseHas('stock_movements', ['type' => StockMovementType::STOCK_TRANSFER_IN]);

        $this->getJson('/api/warehouse-transfers?status=received')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
