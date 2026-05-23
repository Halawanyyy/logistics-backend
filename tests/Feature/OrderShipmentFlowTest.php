<?php

namespace Tests\Feature;

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
}
