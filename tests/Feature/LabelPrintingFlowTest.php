<?php

namespace Tests\Feature;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class LabelPrintingFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_existing_product_barcode_label_endpoint_stays_backward_compatible(): void
    {
        $merchant = $this->makeMerchant();

        $product = WarehouseProduct::create([
            'merchant_id' => $merchant->id,
            'name' => 'Classic Mug',
            'sku' => 'MUG-001',
            'status' => 'active',
        ]);

        $response = $this->get('/api/warehouse-products/'.$product->id.'/barcode-label');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Classic Mug');
        $response->assertSee('SKU:');
    }

    public function test_product_labels_support_html_pdf_zpl_and_tspl(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);

        $product = WarehouseProduct::create([
            'merchant_id' => $merchant->id,
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-001',
            'status' => 'active',
        ]);

        InventoryStock::create([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $product->id,
            'quantity_available' => 5,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
        ]);

        $html = $this->get('/api/warehouse-products/'.$product->id.'/label?format=html');
        $html->assertOk();
        $html->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html->assertSee('Wireless Mouse');

        $pdf = $this->get('/api/warehouse-products/'.$product->id.'/label?format=pdf');
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());

        $zpl = $this->get('/api/warehouse-products/'.$product->id.'/label?format=zpl');
        $zpl->assertOk();
        $zpl->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $zpl->assertSee('^XA');
        $zpl->assertSee('^BCN');

        $tspl = $this->get('/api/warehouse-products/'.$product->id.'/label?format=tspl');
        $tspl->assertOk();
        $tspl->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $tspl->assertSee('SIZE');
        $tspl->assertSee('BARCODE');
    }

    public function test_bulk_product_labels_respect_requested_quantity(): void
    {
        $merchant = $this->makeMerchant();

        $productOne = WarehouseProduct::create([
            'merchant_id' => $merchant->id,
            'name' => 'Notebook',
            'sku' => 'NOTE-001',
            'status' => 'active',
        ]);

        $productTwo = WarehouseProduct::create([
            'merchant_id' => $merchant->id,
            'name' => 'Pen',
            'sku' => 'PEN-001',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/warehouse-products/labels/bulk?format=html', [
            'items' => [
                ['warehouse_product_id' => $productOne->id, 'quantity' => 2],
                ['warehouse_product_id' => $productTwo->id, 'quantity' => 3],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(5, substr_count($response->getContent(), 'class="label-page"'));
    }

    public function test_shipment_labels_and_bulk_waybills_work(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();

        $orderOne = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'cod_amount' => '180.00',
        ]);
        $orderTwo = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'cod_amount' => '220.00',
        ]);

        $shipmentOne = $this->makeShipment($orderOne);
        $shipmentTwo = $this->makeShipment($orderTwo);

        $single = $this->get('/api/shipments/'.$shipmentOne->id.'/waybill?format=html');
        $single->assertOk();
        $single->assertSee($shipmentOne->shipment_number);
        $single->assertSee($orderOne->customer_name);
        $single->assertSee((string) $orderOne->cod_amount);

        $bulk = $this->postJson('/api/shipments/labels/bulk?format=zpl', [
            'shipment_ids' => [$shipmentOne->id, $shipmentTwo->id],
        ]);

        $bulk->assertOk();
        $bulk->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $bulk->assertSee('^XA');
        $bulk->assertSee($shipmentOne->shipment_number);
        $bulk->assertSee($shipmentTwo->shipment_number);
    }

    public function test_order_and_bin_labels_work_and_default_format_can_be_overridden(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $warehouse = $this->makeWarehouse($governorate, $area);
        $order = $this->makeOrder($merchant, $governorate, $area);

        $bin = WarehouseBin::create([
            'warehouse_id' => $warehouse->id,
            'name' => 'Pick Bin 01',
            'code' => 'BIN-01',
            'status' => 'active',
        ]);

        SystemSetting::query()
            ->where('key', 'default_label_format')
            ->update(['value' => 'zpl']);

        $defaultOrder = $this->get('/api/orders/'.$order->id.'/label');
        $defaultOrder->assertOk();
        $defaultOrder->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $defaultOrder->assertSee('^XA');

        $overrideOrder = $this->get('/api/orders/'.$order->id.'/label?format=html');
        $overrideOrder->assertOk();
        $overrideOrder->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $overrideOrder->assertSee($order->order_number);

        $binLabel = $this->get('/api/warehouse-bins/'.$bin->id.'/label?format=tspl');
        $binLabel->assertOk();
        $binLabel->assertSee('BARCODE');
        $binLabel->assertSee('BIN-01');
    }

    public function test_invalid_format_returns_validation_error_and_generic_preview_endpoints_work(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area);

        $this->getJson('/api/orders/'.$order->id.'/label?format=png')
            ->assertStatus(422)
            ->assertJsonValidationErrors('format');

        $this->getJson('/api/labels/templates')
            ->assertOk()
            ->assertJsonPath('data.templates.0.type', 'warehouse_product');

        $this->getJson('/api/labels/settings')
            ->assertOk()
            ->assertJsonPath('data.defaults.format', 'html');

        $this->postJson('/api/labels/preview', [
            'label_type' => 'shipment',
            'format' => 'json',
        ])->assertOk()
            ->assertJsonPath('label_type', 'shipment')
            ->assertJsonPath('format', 'json');

        $this->postJson('/api/labels/test', [
            'label_type' => 'warehouse_product',
            'format' => 'tspl',
        ])->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('PRINT 1,1');
    }

    public function test_driver_manifest_print_remains_backward_compatible_and_supports_html(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $driver = $this->makeDriver();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
        ]);

        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data.id');

        $this->patchJson('/api/shipments/'.$shipmentId.'/status', [
            'status' => ShipmentStatus::READY_FOR_DISPATCH,
        ])->assertOk();

        $manifestId = $this->postJson('/api/driver-manifests/generate', [
            'driver_id' => $driver->id,
            'manifest_date' => '2026-05-29',
            'shipment_ids' => [$shipmentId],
        ])->assertCreated()->json('data.id');

        $json = $this->getJson('/api/driver-manifests/'.$manifestId.'/print');
        $json->assertOk();
        $json->assertJsonPath('data.manifest.total_shipments', 1);

        $html = $this->get('/api/driver-manifests/'.$manifestId.'/print?format=html');
        $html->assertOk();
        $html->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html->assertSee('Driver Manifest');
    }
}
