<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class BackendFinalAuditTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_public_tracking_is_redacted_but_keeps_safe_progress_fields(): void
    {
        $merchant = $this->makeMerchant();
        [$governorate, $area] = $this->makeLocation();
        $order = $this->makeOrder($merchant, $governorate, $area, [
            'status' => 'confirmed',
            'customer_name' => 'Sensitive Customer',
            'delivery_address' => 'Building 10, Street 12, Cairo',
        ]);
        $shipment = $this->makeShipment($order, [
            'status' => ShipmentStatus::IN_TRANSIT,
            'customer_name' => 'Sensitive Customer',
            'delivery_address' => 'Building 10, Street 12, Cairo',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);

        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::PENDING_PICKUP,
            'notes' => 'Internal pickup note.',
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);
        ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::IN_TRANSIT,
            'notes' => 'Internal hub transition note.',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/api/track/'.$shipment->shipment_number);

        $response
            ->assertOk()
            ->assertJsonPath('data.shipment_number', $shipment->shipment_number)
            ->assertJsonPath('data.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.current_status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.status_label', 'In Transit')
            ->assertJsonPath('data.delivery_governorate_name', $governorate->name)
            ->assertJsonPath('data.delivery_area_name', $area->name)
            ->assertJsonPath('data.latest_tracking_event.status', ShipmentStatus::IN_TRANSIT)
            ->assertJsonMissingPath('data.customer_name')
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.delivery_address')
            ->assertJsonMissingPath('data.merchant_id')
            ->assertJsonMissingPath('data.assigned_driver_id')
            ->assertJsonMissingPath('data.tracking_notes')
            ->assertJsonMissingPath('data.latest_tracking_event.notes')
            ->assertJsonMissingPath('data.tracking_events.0.notes');

        $trackingEvents = $response->json('data.tracking_events');
        $this->assertCount(2, $trackingEvents);
        $this->assertSame(
            [ShipmentStatus::PENDING_PICKUP, ShipmentStatus::IN_TRANSIT],
            array_column($trackingEvents, 'status')
        );
        $this->assertSame(
            ['Pending Pickup', 'In Transit'],
            array_column($trackingEvents, 'status_label')
        );
        $this->assertSame($trackingEvents, $response->json('data.histories'));
        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
    }

    public function test_integration_orders_reject_submitted_merchant_id(): void
    {
        [$merchant, $plainToken] = $this->createIntegrationTokenPair();
        [$governorate, $area] = $this->makeLocation();
        $spoofMerchant = $this->makeMerchant([
            'email' => 'spoof-audit@example.com',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/integrations/orders', [
                'merchant_id' => $spoofMerchant->id,
                'external_source' => 'woocommerce',
                'external_order_id' => 'AUDIT-1001',
                'customer_name' => 'Integration Customer',
                'customer_phone' => '01000000000',
                'delivery_governorate_id' => $governorate->id,
                'delivery_area_id' => $area->id,
                'delivery_address' => 'Nasr City, Cairo',
                'payment_type' => 'cod',
                'cod_amount' => 250,
                'fulfillment_type' => 'pickup_from_merchant',
                'items' => [[
                    'product_name' => 'Audit Item',
                    'quantity' => 1,
                    'unit_price' => 250,
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->assertDatabaseMissing('orders', [
            'merchant_id' => $merchant->id,
            'external_source' => 'woocommerce',
            'external_order_id' => 'AUDIT-1001',
        ]);
    }

    public function test_public_route_whitelist_and_selected_protected_routes_are_enforced(): void
    {
        $this->getJson('/api/auth/ping')->assertOk();
        $this->getJson('/api/public/settings')->assertOk();
        $this->getJson('/api/track/UNKNOWN-SHIPMENT')->assertStatus(404);

        $this->getJson('/api/orders')->assertStatus(401);
        $this->getJson('/api/users')->assertStatus(401);
        $this->getJson('/api/shipments')->assertStatus(401);
        $this->getJson('/api/attachments')->assertStatus(401);
        $this->getJson('/api/exports')->assertStatus(401);
        $this->getJson('/api/merchant-portal/me')->assertStatus(401);
        $this->getJson('/api/integrations/me')->assertStatus(401);
    }

    public function test_merchant_portal_requests_reject_merchant_id_override(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'portal-audit-b@example.com']);
        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/me?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');
    }

    /**
     * @return array{0: \App\Models\Merchant, 1: string}
     */
    private function createIntegrationTokenPair(): array
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/merchants/'.$merchant->id.'/integration-tokens', [
            'name' => 'Audit Token',
            'provider' => 'woocommerce',
            'permissions' => [
                'orders.create',
                'orders.view',
                'shipments.view',
                'tracking.view',
            ],
        ])->assertCreated();

        return [$merchant, $response->json('data.plain_token')];
    }
}
