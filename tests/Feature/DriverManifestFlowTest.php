<?php

namespace Tests\Feature;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Shipment\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class DriverManifestFlowTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_generate_complete_and_close_manifest_creates_cash_closure(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $driver = $this->makeDriver();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'cod_amount' => '300.00',
            'shipping_fee' => '30.00',
        ], [[
            'product_name' => 'Phone',
            'quantity' => 1,
            'unit_price' => '300.00',
        ]]);

        $orderItemId = $order->items->first()->id;

        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data.id');

        $this->patchJson('/api/shipments/'.$shipmentId.'/status', [
            'status' => ShipmentStatus::READY_FOR_DISPATCH,
            'notes' => 'Ready for manifest',
        ])->assertOk();

        $manifestResponse = $this->postJson('/api/driver-manifests/generate', [
            'driver_id' => $driver->id,
            'manifest_date' => '2026-05-23',
            'shipment_ids' => [$shipmentId],
            'notes' => 'Morning batch',
        ]);

        $manifestId = $manifestResponse->json('data.id');

        $manifestResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipmentId,
            'status' => ShipmentStatus::OUT_FOR_DELIVERY,
            'assigned_driver_id' => $driver->id,
        ]);

        $this->postJson('/api/shipments/'.$shipmentId.'/complete-delivery', [
            'shipment_status' => ShipmentStatus::DELIVERED,
            'notes' => 'Delivered successfully',
            'items' => [
                [
                    'order_item_id' => $orderItemId,
                    'result_status' => 'delivered',
                    'quantity' => 1,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', ShipmentStatus::DELIVERED);

        $this->postJson('/api/driver-manifests/'.$manifestId.'/close', [
            'actual_received_amount' => 300,
            'notes' => 'Driver returned cash',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_closure.expected_amount', '300.00');

        $this->assertDatabaseHas('driver_cash_closures', [
            'driver_manifest_id' => $manifestId,
            'driver_id' => $driver->id,
        ]);
    }
}
