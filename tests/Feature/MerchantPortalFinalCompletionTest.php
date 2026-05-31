<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalFinalCompletionTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
        Storage::fake('attachments');
        Storage::fake('exports');
    }

    public function test_dashboard_summary_is_merchant_scoped_and_respects_capability_sections(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'dashboard-b@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);

        $orderA = $this->makeOrder($merchantA, $governorate, $area, [
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeOrder($merchantB, $governorate, $area, [
            'status' => 'confirmed',
        ]);

        $shipmentA = $this->makeShipment($orderA, [
            'status' => 'in_transit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productA = $this->makeWarehouseProduct($merchantA, [
            'minimum_stock_quantity' => 10,
        ]);
        $this->makeWarehouseProduct($merchantB, [
            'sku' => 'SKU-OTHER-DASH',
            'barcode' => 'OTHER-DASH',
        ]);
        $this->makeInventoryStock($warehouse, $productA, [
            'quantity_available' => 7,
            'quantity_reserved' => 3,
        ]);

        $this->makeMerchantWalletTransaction($merchantA, [
            'transaction_number' => 'MWT-DASH-000001',
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '250.00',
            'balance_after' => '250.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $invoice = $this->makeMerchantInvoice($merchantA, [
            'invoice_number' => 'INV-DASH-000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payout = $this->makeMerchantPayout($merchantA, [
            'reference_number' => 'PAYOUT-DASH-000001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MerchantInboundStockRequest::create([
            'request_number' => 'MISR-DASH-000001',
            'merchant_id' => $merchantA->id,
            'warehouse_id' => $warehouse->id,
            'status' => MerchantInboundStockRequestStatus::SUBMITTED,
            'requested_by' => User::factory()->create()->id,
            'submitted_at' => now(),
        ]);

        $owner = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $owner, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/merchant-portal/dashboard/summary?date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.orders.total_orders', 1)
            ->assertJsonPath('data.orders.delivered_orders', 1)
            ->assertJsonPath('data.shipments.total_shipments', 1)
            ->assertJsonPath('data.shipments.in_transit_shipments', 1)
            ->assertJsonPath('data.warehouse.warehouse_products_count', 1)
            ->assertJsonPath('data.warehouse.low_stock_count', 1)
            ->assertJsonPath('data.warehouse.total_available_quantity', 7)
            ->assertJsonPath('data.warehouse.total_reserved_quantity', 3)
            ->assertJsonPath('data.warehouse.inbound_requests_pending_count', 1)
            ->assertJsonPath('data.finance.latest_invoice.id', $invoice->id)
            ->assertJsonPath('data.finance.last_payout.id', $payout->id)
            ->assertJsonPath('data.latest_orders.0.id', $orderA->id)
            ->assertJsonPath('data.latest_shipments.0.id', $shipmentA->id);

        $this->getJson('/api/merchant-portal/dashboard/summary?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $warehouseViewer = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $warehouseViewer, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($warehouseViewer->fresh());

        $this->getJson('/api/merchant-portal/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.warehouse.warehouse_products_count', 1)
            ->assertJsonPath('data.finance', null);
    }

    public function test_merchant_can_view_and_update_safe_profile_and_settings_fields_only(): void
    {
        $merchant = $this->makeMerchant();
        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, [
            'role' => MerchantPortalRole::OWNER,
        ]);
        $this->makeMerchantPortalSetting($merchant);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $merchant->id);

        $this->putJson('/api/merchant-portal/profile', [
            'name' => 'Updated Merchant',
            'company_name' => 'Updated Merchant Co',
            'phone' => '01234567890',
            'email' => 'updated-merchant@example.com',
            'address' => 'Updated Address',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Merchant')
            ->assertJsonPath('data.email', 'updated-merchant@example.com');

        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'name' => 'Updated Merchant',
            'email' => 'updated-merchant@example.com',
        ]);

        $this->putJson('/api/merchant-portal/profile', [
            'merchant_id' => 999,
            'status' => 'inactive',
            'pricing_plan_id' => 99,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_id', 'status', 'pricing_plan_id']);

        $this->getJson('/api/merchant-portal/settings')
            ->assertOk()
            ->assertJsonPath('data.default_label_format', 'html');

        $this->putJson('/api/merchant-portal/settings', [
            'default_fulfillment_type' => 'pickup_from_merchant',
            'default_payment_type' => 'cod',
            'default_pickup_address' => 'Warehouse pickup desk',
            'default_label_format' => 'zpl',
            'notification_preferences' => [
                'email_enabled' => true,
                'low_stock' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.default_label_format', 'zpl')
            ->assertJsonPath('data.notification_preferences.email_enabled', true);

        $this->assertDatabaseHas('merchant_portal_settings', [
            'merchant_id' => $merchant->id,
            'default_label_format' => 'zpl',
        ]);

        $this->putJson('/api/merchant-portal/settings', [
            'merchant_id' => 1,
            'status' => 'inactive',
            'internal_notes' => 'not allowed',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_id', 'status', 'internal_notes']);
    }

    public function test_merchant_can_manage_only_own_integration_tokens_and_test_safe_integration_docs(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'integration-b@example.com']);
        $otherToken = $this->makeMerchantIntegrationToken($merchantB, [
            'name' => 'Other Token',
            'token_prefix' => 'mit_other_prefix...',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($user->fresh());

        $createResponse = $this->postJson('/api/merchant-portal/integration-tokens', [
            'name' => 'WooCommerce Store',
            'expires_at' => now()->addMonth()->toDateString(),
            'abilities' => ['orders.create', 'orders.view', 'shipments.view'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'WooCommerce Store')
            ->assertJsonMissingPath('data.token_hash');

        $tokenId = $createResponse->json('data.id');
        $plainToken = $createResponse->json('data.plain_token');

        $this->assertIsString($plainToken);
        $this->assertStringStartsWith('mit_live_', $plainToken);

        $this->getJson('/api/merchant-portal/integration-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/merchant-portal/integration-tokens/'.$tokenId)
            ->assertOk()
            ->assertJsonPath('data.id', $tokenId)
            ->assertJsonMissingPath('data.plain_token')
            ->assertJsonMissingPath('data.token_hash');

        $this->postJson('/api/merchant-portal/integrations/test', [
            'abilities' => ['integrations.me', 'tracking.view'],
        ])->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.base_path', '/api/integrations');

        $this->getJson('/api/merchant-portal/integrations/docs')
            ->assertOk()
            ->assertJsonPath('data.base_path', '/api/integrations')
            ->assertJsonPath('data.create_order_example.external_source', 'woocommerce');

        $this->postJson('/api/merchant-portal/integration-tokens', [
            'merchant_id' => $merchantB->id,
            'name' => 'Bad Token',
            'abilities' => ['finance.manage'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->postJson('/api/merchant-portal/integrations/test', [
            'abilities' => ['finance.manage'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('abilities');

        $this->postJson('/api/merchant-portal/integration-tokens/'.$tokenId.'/revoke', [
            'notes' => 'Rotate token',
        ])->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->deleteJson('/api/merchant-portal/integration-tokens/'.$tokenId)
            ->assertOk();

        $this->assertSoftDeleted('merchant_integration_tokens', [
            'id' => $tokenId,
        ]);

        $this->getJson('/api/merchant-portal/integration-tokens/'.$otherToken->id)
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/integration-tokens/'.$otherToken->id.'/revoke')
            ->assertStatus(404);
    }

    public function test_merchant_can_create_list_and_download_only_own_scoped_exports(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'exports-b@example.com']);
        $order = $this->makeOrder($merchantA, $governorate, $area, [
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherExport = $this->makeExportLog([
            'merchant_id' => $merchantB->id,
            'export_type' => 'orders',
            'file_path' => 'exports/other-merchant.csv',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/exports/types')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'orders');

        $response = $this->postJson('/api/merchant-portal/exports', [
            'type' => 'orders',
            'filters' => [
                'date_from' => now()->subDay()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
                'status' => 'confirmed',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.type', 'orders')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonMissingPath('data.file_path');

        $exportId = $response->json('data.id');

        $this->assertDatabaseHas('export_logs', [
            'id' => $exportId,
            'merchant_id' => $merchantA->id,
            'export_type' => 'orders',
            'status' => 'completed',
        ]);

        $this->getJson('/api/merchant-portal/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/merchant-portal/exports/'.$exportId)
            ->assertOk()
            ->assertJsonPath('data.id', $exportId);

        $this->get('/api/merchant-portal/exports/'.$exportId.'/download')
            ->assertOk();

        $this->postJson('/api/merchant-portal/exports', [
            'type' => 'orders',
            'filters' => [
                'merchant_id' => $merchantB->id,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('filters.merchant_id');

        $this->getJson('/api/merchant-portal/exports/'.$otherExport->id)
            ->assertStatus(404);

        $this->get('/api/merchant-portal/exports/'.$otherExport->id.'/download')
            ->assertStatus(404);

        $this->assertNotNull($order->id);
    }

    public function test_merchant_can_manage_only_own_portal_attachments_without_exposing_paths(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'attachments-b@example.com']);
        $orderA = $this->makeOrder($merchantA, $governorate, $area);
        $orderB = $this->makeOrder($merchantB, $governorate, $area, [
            'order_number' => 'ORD-ATTACH-OTHER',
        ]);
        $otherAttachment = $this->makeAttachment($orderB);

        $owner = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $owner, [
            'role' => MerchantPortalRole::OWNER,
        ]);

        Sanctum::actingAs($owner->fresh());

        $uploadResponse = $this->postJson('/api/merchant-portal/attachments', [
            'context' => 'order',
            'resource_id' => $orderA->id,
            'notes' => 'Customer ID copy',
            'file' => UploadedFile::fake()->create('customer-id.pdf', 150, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.context', 'order')
            ->assertJsonPath('data.resource_id', $orderA->id)
            ->assertJsonMissingPath('data.file_path');

        $attachmentId = $uploadResponse->json('data.id');

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'merchant_id' => $merchantA->id,
            'attachable_type' => 'orders',
            'attachable_id' => $orderA->id,
            'merchant_uploaded' => true,
        ]);

        $this->getJson('/api/merchant-portal/attachments')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/merchant-portal/attachments/'.$attachmentId)
            ->assertOk()
            ->assertJsonPath('data.id', $attachmentId)
            ->assertJsonMissingPath('data.file_path');

        $this->get('/api/merchant-portal/attachments/'.$attachmentId.'/download')
            ->assertOk();

        $this->postJson('/api/merchant-portal/attachments', [
            'merchant_id' => $merchantB->id,
            'context' => 'order',
            'resource_id' => $orderA->id,
            'file' => UploadedFile::fake()->create('bad.pdf', 120, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->postJson('/api/merchant-portal/attachments', [
            'context' => 'order',
            'resource_id' => $orderB->id,
            'file' => UploadedFile::fake()->create('cross-merchant.pdf', 120, 'application/pdf'),
        ])->assertStatus(404);

        $this->getJson('/api/merchant-portal/attachments/'.$otherAttachment->id)
            ->assertStatus(404);

        $this->deleteJson('/api/merchant-portal/attachments/'.$attachmentId)
            ->assertOk();

        $this->assertSoftDeleted('attachments', [
            'id' => $attachmentId,
        ]);
    }

    public function test_merchant_notifications_are_scoped_and_mark_read_actions_only_affect_own_notifications(): void
    {
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'notifications-b@example.com']);
        $notificationA1 = $this->makeMerchantNotification($merchantA, [
            'type' => 'invoice_issued',
            'title' => 'Invoice ready',
        ]);
        $notificationA2 = $this->makeMerchantNotification($merchantA, [
            'type' => 'low_stock',
            'title' => 'Low stock alert',
        ]);
        $notificationB = $this->makeMerchantNotification($merchantB, [
            'type' => 'payout_status_changed',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/notifications?unread_only=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/merchant-portal/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->postJson('/api/merchant-portal/notifications/'.$notificationA1->id.'/mark-read')
            ->assertOk()
            ->assertJsonPath('data.id', $notificationA1->id);

        $this->assertDatabaseMissing('merchant_notifications', [
            'id' => $notificationA1->id,
            'read_at' => null,
        ]);

        $this->postJson('/api/merchant-portal/notifications/'.$notificationB->id.'/mark-read')
            ->assertStatus(404);

        $this->postJson('/api/merchant-portal/notifications/mark-all-read')
            ->assertOk();

        $this->assertDatabaseMissing('merchant_notifications', [
            'id' => $notificationA2->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('merchant_notifications', [
            'id' => $notificationB->id,
            'read_at' => null,
        ]);

        $this->getJson('/api/merchant-portal/notifications?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');
    }

    public function test_final_phase_capabilities_are_enforced_for_portal_roles(): void
    {
        $merchant = $this->makeMerchant();
        $accountantUser = User::factory()->create(['role' => 'merchant_portal']);
        $staffUser = User::factory()->create(['role' => 'merchant_portal']);
        $warehouseViewerUser = User::factory()->create(['role' => 'merchant_portal']);

        $this->makeMerchantPortalUser($merchant, $accountantUser, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);
        $this->makeMerchantPortalUser($merchant, $staffUser, [
            'role' => MerchantPortalRole::STAFF,
            'is_primary' => false,
        ]);
        $this->makeMerchantPortalUser($merchant, $warehouseViewerUser, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($staffUser->fresh());
        $this->getJson('/api/merchant-portal/finance/summary')->assertStatus(403);
        $this->getJson('/api/merchant-portal/integration-tokens')->assertStatus(403);
        $this->getJson('/api/merchant-portal/exports/types')->assertStatus(403);

        Sanctum::actingAs($accountantUser->fresh());
        $this->getJson('/api/merchant-portal/finance/summary')->assertOk();
        $this->getJson('/api/merchant-portal/exports/types')->assertOk();
        $this->postJson('/api/merchant-portal/integration-tokens', [
            'name' => 'Blocked',
            'abilities' => ['orders.view'],
        ])->assertStatus(403);
        $this->postJson('/api/merchant-portal/attachments', [
            'context' => 'merchant_profile',
            'file' => UploadedFile::fake()->create('blocked.pdf', 50, 'application/pdf'),
        ])->assertStatus(403);

        Sanctum::actingAs($warehouseViewerUser->fresh());
        $this->getJson('/api/merchant-portal/dashboard/summary')->assertOk();
        $this->getJson('/api/merchant-portal/finance/summary')->assertStatus(403);
        $this->getJson('/api/merchant-portal/exports/types')->assertOk();
    }

    public function test_existing_internal_integrations_attachments_and_exports_apis_still_work(): void
    {
        $merchant = $this->makeMerchant();
        $this->actingAsSuperAdmin();

        $this->postJson('/api/merchants/'.$merchant->id.'/integration-tokens', [
            'name' => 'Internal Token',
            'provider' => 'woocommerce',
            'permissions' => ['orders.create', 'orders.view'],
        ])->assertCreated();

        $this->postJson('/api/attachments', [
            'attachable_type' => 'merchants',
            'attachable_id' => $merchant->id,
            'category' => 'document',
            'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
        ])->assertCreated();

        $this->postJson('/api/exports/audit_logs', [
            'format' => 'csv',
        ])->assertCreated();
    }
}
