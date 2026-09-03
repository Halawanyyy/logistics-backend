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

        $closeResponse = $this->postJson('/api/driver-manifests/'.$manifestId.'/close', [
            'actual_received_amount' => 300,
            'notes' => 'Driver returned cash',
        ]);

        $cashClosureId = $closeResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.cash_closure.expected_amount', '300.00')
            ->assertJsonPath('data.cash_closure.status', 'pending')
            ->assertJsonPath('data.cash_closure.verified_by', null)
            ->json('data.cash_closure.id');

        $this->assertDatabaseHas('driver_cash_closures', [
            'driver_manifest_id' => $manifestId,
            'driver_id' => $driver->id,
            'status' => 'pending',
        ]);

        $admin = $this->actingAsRole('admin');

        $this->postJson('/api/driver-cash-closures/'.$cashClosureId.'/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.verified_by', $admin->id);
    }

    public function test_manifest_cannot_close_until_every_shipment_is_delivered(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $driver = $this->makeDriver();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => OrderStatus::CONFIRMED,
            'cod_amount' => '300.00',
        ]);

        $shipmentId = $this->postJson('/api/orders/'.$order->id.'/shipments')
            ->assertOk()
            ->json('data.id');

        $this->patchJson('/api/shipments/'.$shipmentId.'/status', [
            'status' => ShipmentStatus::READY_FOR_DISPATCH,
        ])->assertOk();

        $manifestId = $this->postJson('/api/driver-manifests/generate', [
            'driver_id' => $driver->id,
            'manifest_date' => '2026-05-24',
            'shipment_ids' => [$shipmentId],
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/driver-manifests/'.$manifestId.'/close', [
            'actual_received_amount' => 300,
        ])->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'until all shipments are delivered'));

        $this->assertDatabaseMissing('driver_cash_closures', [
            'driver_manifest_id' => $manifestId,
        ]);
    }
}
