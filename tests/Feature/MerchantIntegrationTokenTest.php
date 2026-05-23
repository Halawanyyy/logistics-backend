<?php

namespace Tests\Feature;

use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantIntegrationTokenTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        $this->actingAsSuperAdmin();
    }

    public function test_admin_can_create_token_and_raw_token_is_only_returned_on_creation(): void
    {
        $merchant = $this->makeMerchant();

        $response = $this->postJson('/api/merchants/'.$merchant->id.'/integration-tokens', [
            'name' => 'WooCommerce Main Store',
            'provider' => 'woocommerce',
            'permissions' => [
                'orders.create',
                'orders.view',
                'shipments.view',
                'tracking.view',
            ],
            'notes' => 'Token for main WooCommerce store',
        ]);

        $tokenId = $response->json('data.id');
        $plainToken = $response->json('data.plain_token');

        $response
            ->assertCreated()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.provider', 'woocommerce')
            ->assertJsonMissingPath('data.token_hash');

        $this->assertIsString($plainToken);
        $this->assertStringStartsWith('mit_live_', $plainToken);

        $this->assertDatabaseHas('merchant_integration_tokens', [
            'id' => $tokenId,
            'merchant_id' => $merchant->id,
            'name' => 'WooCommerce Main Store',
        ]);

        $showResponse = $this->getJson('/api/merchant-integration-tokens/'.$tokenId);

        $showResponse
            ->assertOk()
            ->assertJsonMissingPath('data.plain_token')
            ->assertJsonMissingPath('data.token_hash');
    }

    public function test_plugin_can_call_integrations_me(): void
    {
        [$merchant, $plainToken] = $this->createTokenPair();

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/integrations/me')
            ->assertOk()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.token_name', 'Plugin Token');
    }

    public function test_revoked_token_cannot_authenticate(): void
    {
        [$merchant, $plainToken, $tokenId] = $this->createTokenPair();

        $this->postJson('/api/merchant-integration-tokens/'.$tokenId.'/revoke', [
            'notes' => 'Plugin replaced',
        ])->assertOk()
            ->assertJsonPath('data.status', MerchantIntegrationToken::STATUS_REVOKED);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/integrations/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Integration token revoked.');

        $this->assertNotNull($merchant->id);
    }

    public function test_expired_token_cannot_authenticate(): void
    {
        [, $plainToken, $tokenId] = $this->createTokenPair();

        MerchantIntegrationToken::query()->whereKey($tokenId)->update([
            'expires_at' => now()->subDay(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/integrations/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Integration token expired.');
    }

    public function test_plugin_can_create_order_with_token_and_prevent_duplicates_and_spoofing(): void
    {
        [$merchant, $plainToken] = $this->createTokenPair();
        [$governorate, $area] = $this->makeLocation();
        $spoofMerchant = $this->makeMerchant([
            'email' => 'spoof@example.com',
        ]);

        $payload = [
            'merchant_id' => $spoofMerchant->id,
            'external_source' => 'woocommerce',
            'external_order_id' => '12345',
            'external_order_number' => 'WC-12345',
            'order_type' => 'delivery',
            'customer_name' => 'Ahmed Adel',
            'customer_phone' => '01000000000',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Nasr City, Cairo',
            'payment_type' => 'cod',
            'cod_amount' => 500,
            'fulfillment_type' => 'pickup_from_merchant',
            'items' => [
                [
                    'product_name' => 'T-Shirt',
                    'sku' => 'TSHIRT-001',
                    'quantity' => 1,
                    'unit_price' => 500,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/integrations/orders', $payload);

        $orderId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertJsonPath('data.merchant_id', $merchant->id)
            ->assertJsonPath('data.source', OrderSource::INTEGRATION)
            ->assertJsonPath('data.external_source', 'woocommerce')
            ->assertJsonPath('data.external_order_id', '12345');

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame($merchant->id, $order->merchant_id);
        $this->assertNotSame($spoofMerchant->id, $order->merchant_id);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/integrations/orders', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Duplicate external order for this merchant and source.');
    }

    /**
     * @return array{0: \App\Models\Merchant, 1: string, 2: int}
     */
    private function createTokenPair(): array
    {
        $merchant = $this->makeMerchant();

        $response = $this->postJson('/api/merchants/'.$merchant->id.'/integration-tokens', [
            'name' => 'Plugin Token',
            'provider' => 'woocommerce',
            'permissions' => [
                'orders.create',
                'orders.view',
                'shipments.view',
                'tracking.view',
            ],
        ])->assertCreated();

        return [
            $merchant,
            $response->json('data.plain_token'),
            $response->json('data.id'),
        ];
    }
}
