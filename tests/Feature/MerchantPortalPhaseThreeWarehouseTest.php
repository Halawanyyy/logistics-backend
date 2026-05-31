<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\WarehouseCharge;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class MerchantPortalPhaseThreeWarehouseTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_merchant_portal_warehouse_endpoints_require_auth_and_active_membership(): void
    {
        $this->getJson('/api/merchant-portal/warehouse/products')
            ->assertStatus(401);

        $this->actingAsRole('staff');

        $this->getJson('/api/merchant-portal/warehouse/products')
            ->assertStatus(403);
    }

    public function test_merchant_user_can_only_access_own_warehouse_entities_and_merchant_id_is_rejected(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchantA = $this->makeMerchant();
        $merchantB = $this->makeMerchant(['email' => 'warehouse-b@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);

        $productA = $this->makeWarehouseProduct($merchantA, ['sku' => 'A-SKU-1']);
        $productB = $this->makeWarehouseProduct($merchantB, ['sku' => 'B-SKU-1']);
        $stockA = $this->makeInventoryStock($warehouse, $productA);
        $stockB = $this->makeInventoryStock($warehouse, $productB);
        $movementA = $this->makeStockMovement($warehouse, $productA, ['reference_id' => 11]);
        $movementB = $this->makeStockMovement($warehouse, $productB, ['reference_id' => 22]);
        $chargeA = $this->makeWarehouseCharge($merchantA, $warehouse, $productA, ['description' => 'Charge A']);
        $chargeB = $this->makeWarehouseCharge($merchantB, $warehouse, $productB, ['description' => 'Charge B']);
        $requestOwner = User::factory()->create(['role' => 'merchant_portal']);

        $requestA = MerchantInboundStockRequest::create([
            'request_number' => 'MIR-A-000001',
            'merchant_id' => $merchantA->id,
            'warehouse_id' => $warehouse->id,
            'status' => MerchantInboundStockRequestStatus::SUBMITTED,
            'requested_by' => $requestOwner->id,
            'submitted_at' => now(),
        ]);
        $requestB = MerchantInboundStockRequest::create([
            'request_number' => 'MIR-B-000001',
            'merchant_id' => $merchantB->id,
            'warehouse_id' => $warehouse->id,
            'status' => MerchantInboundStockRequestStatus::SUBMITTED,
            'requested_by' => $requestOwner->id,
            'submitted_at' => now(),
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchantA, $user, ['role' => MerchantPortalRole::OWNER]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/warehouse/products?merchant_id='.$merchantB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'merchant_id' => $merchantB->id,
            'items' => [[
                'warehouse_product_id' => $productA->id,
                'quantity' => 10,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('merchant_id');

        $this->getJson('/api/merchant-portal/warehouse/products/'.$productA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $productA->id);

        $this->getJson('/api/merchant-portal/warehouse/products/'.$productB->id)
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/warehouse/inventory/'.$stockA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $stockA->id);

        $this->getJson('/api/merchant-portal/warehouse/inventory/'.$stockB->id)
            ->assertStatus(404);

        $this->getJson('/api/merchant-portal/warehouse/movements?reference_id='.$movementA->reference_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movementA->id);

        $this->getJson('/api/merchant-portal/warehouse/movements?reference_id='.$movementB->reference_id)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/merchant-portal/warehouse/charges?search=Charge')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $chargeA->id);

        $this->getJson('/api/merchant-portal/warehouse/inbound-requests/'.$requestA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $requestA->id);

        $this->getJson('/api/merchant-portal/warehouse/inbound-requests/'.$requestB->id)
            ->assertStatus(404);

        $this->assertNotNull($chargeB->id);
    }

    public function test_merchant_can_list_show_and_print_only_own_products(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-product@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);

        $product = $this->makeWarehouseProduct($merchant, [
            'name' => 'Portal Product',
            'sku' => 'PORTAL-SKU',
            'barcode' => 'PORTAL-BARCODE',
        ]);
        $otherProduct = $this->makeWarehouseProduct($otherMerchant, ['name' => 'Other Product']);

        $this->makeInventoryStock($warehouse, $product, [
            'quantity_available' => 3,
            'quantity_reserved' => 2,
        ]);

        $this->makeStockMovement($warehouse, $product, [
            'type' => StockMovementType::STOCK_IN,
            'quantity' => 5,
            'reference_id' => 100,
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, ['role' => MerchantPortalRole::OWNER]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/warehouse/products?search=Portal&has_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id);

        $this->getJson('/api/merchant-portal/warehouse/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.current_stock_summary.available_quantity', 3)
            ->assertJsonPath('data.current_stock_summary.reserved_quantity', 2);

        $this->get('/api/merchant-portal/warehouse/products/'.$product->id.'/label?format=html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Portal Product');

        $this->get('/api/merchant-portal/warehouse/products/'.$otherProduct->id.'/label?format=html')
            ->assertStatus(404);

        $this->post('/api/merchant-portal/warehouse/products/labels/bulk', [
            'format' => 'zpl',
            'items' => [[
                'warehouse_product_id' => $product->id,
                'quantity' => 2,
            ]],
        ])->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('^XA');
    }

    public function test_merchant_can_list_own_inventory_low_stock_and_movements_but_cannot_adjust_stock_from_portal(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-inventory@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);

        $product = $this->makeWarehouseProduct($merchant, [
            'name' => 'Low Stock Product',
            'sku' => 'LOW-SKU',
            'minimum_stock_quantity' => 5,
            'low_stock_alert_enabled' => true,
        ]);
        $otherProduct = $this->makeWarehouseProduct($otherMerchant, ['sku' => 'OTHER-STOCK']);
        $stock = $this->makeInventoryStock($warehouse, $product, [
            'quantity_available' => 2,
            'quantity_reserved' => 1,
        ]);
        $this->makeInventoryStock($warehouse, $otherProduct, [
            'quantity_available' => 1,
        ]);
        $movement = $this->makeStockMovement($warehouse, $product, [
            'type' => StockMovementType::RESERVED,
            'quantity' => 1,
            'reference_type' => 'order',
            'reference_id' => 777,
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, ['role' => MerchantPortalRole::STAFF, 'is_primary' => false]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/warehouse/inventory?search=Low&available_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stock->id);

        $this->getJson('/api/merchant-portal/warehouse/inventory/low-stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stock->id);

        $this->getJson('/api/merchant-portal/warehouse/movements?movement_type=reserved&reference_id=777')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movement->id);

        $this->postJson('/api/merchant-portal/warehouse/inventory-stocks/adjust', [
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $product->id,
            'type' => 'in',
            'quantity' => 5,
        ])->assertStatus(404);
    }

    public function test_merchant_can_list_own_warehouse_charges_only_and_cannot_manage_them_from_portal(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-charge@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);

        $product = $this->makeWarehouseProduct($merchant);
        $charge = $this->makeWarehouseCharge($merchant, $warehouse, $product, [
            'type' => 'storage',
            'description' => 'Monthly storage',
        ]);
        $this->makeWarehouseCharge($otherMerchant, $warehouse, null, [
            'description' => 'Other merchant charge',
        ]);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, ['role' => MerchantPortalRole::ACCOUNTANT, 'is_primary' => false]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/merchant-portal/warehouse/charges?charge_type=storage&search=Monthly')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $charge->id);

        $this->postJson('/api/merchant-portal/warehouse/charges', [
            'type' => 'storage',
            'amount' => 25,
        ])->assertStatus(405);
    }

    public function test_merchant_can_create_and_view_inbound_requests_without_increasing_stock_or_creating_movements(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $warehouse = $this->makeWarehouse($governorate, $area);
        $product = $this->makeWarehouseProduct($merchant, ['sku' => 'OWN-INBOUND-SKU']);
        $initialMovementCount = StockMovement::query()->count();

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, ['role' => MerchantPortalRole::STAFF, 'is_primary' => false]);

        Sanctum::actingAs($user->fresh());

        $response = $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'warehouse_id' => $warehouse->id,
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'notes' => 'Sending inventory tomorrow',
            'items' => [
                [
                    'warehouse_product_id' => $product->id,
                    'quantity' => 50,
                    'notes' => 'Existing SKU',
                ],
                [
                    'requested_product_name' => 'New SKU Product',
                    'requested_sku' => 'NEW-SKU-001',
                    'quantity' => 20,
                    'notes' => 'New SKU',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', MerchantInboundStockRequestStatus::SUBMITTED)
            ->assertJsonPath('data.items.0.warehouse_product_id', $product->id)
            ->assertJsonPath('data.items.1.sku', 'NEW-SKU-001');

        $requestId = $response->json('data.id');
        $requestNumber = $response->json('data.request_number');

        $this->assertDatabaseHas('merchant_inbound_stock_requests', [
            'id' => $requestId,
            'merchant_id' => $merchant->id,
            'requested_by' => $user->id,
            'status' => MerchantInboundStockRequestStatus::SUBMITTED,
        ]);
        $this->assertNotSame('', (string) $requestNumber);

        $this->assertDatabaseCount('inventory_stocks', 0);
        $this->assertSame($initialMovementCount, StockMovement::query()->count());

        $this->getJson('/api/merchant-portal/warehouse/inbound-requests?status=submitted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $requestId);

        $this->getJson('/api/merchant-portal/warehouse/inbound-requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.allowed_actions.can_cancel', true);

        $secondResponse = $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'warehouse_product_id' => $product->id,
                'quantity' => 5,
            ]],
        ])->assertCreated();

        $this->assertNotSame($requestNumber, $secondResponse->json('data.request_number'));

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests/'.$requestId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', MerchantInboundStockRequestStatus::CANCELLED);

        $this->assertSame($initialMovementCount, StockMovement::query()->count());
    }

    public function test_inbound_request_validation_blocks_other_merchant_products_duplicates_and_unsafe_cancellation(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $otherMerchant = $this->makeMerchant(['email' => 'other-inbound@example.com']);
        $warehouse = $this->makeWarehouse($governorate, $area);
        $ownProduct = $this->makeWarehouseProduct($merchant, ['sku' => 'OWN-REQ-1']);
        $otherProduct = $this->makeWarehouseProduct($otherMerchant, ['sku' => 'OTHER-REQ-1']);

        $user = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $user, ['role' => MerchantPortalRole::STAFF, 'is_primary' => false]);

        Sanctum::actingAs($user->fresh());

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'warehouse_product_id' => $otherProduct->id,
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items.0.warehouse_product_id');

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'warehouse_product_id' => $ownProduct->id,
                    'quantity' => 5,
                ],
                [
                    'warehouse_product_id' => $ownProduct->id,
                    'quantity' => 3,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items.1.warehouse_product_id');

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'requested_product_name' => 'New One',
                    'requested_sku' => 'DUP-SKU',
                    'quantity' => 5,
                ],
                [
                    'requested_product_name' => 'New Two',
                    'requested_sku' => 'dup-sku',
                    'quantity' => 3,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items.1.requested_sku');

        $receivedRequest = MerchantInboundStockRequest::create([
            'request_number' => 'MIR-TEST-000001',
            'merchant_id' => $merchant->id,
            'warehouse_id' => $warehouse->id,
            'status' => MerchantInboundStockRequestStatus::RECEIVED,
            'requested_by' => $user->id,
            'submitted_at' => now(),
            'received_at' => now(),
        ]);
        $receivedRequest->items()->create([
            'warehouse_product_id' => $ownProduct->id,
            'quantity' => 10,
        ]);

        $this->postJson('/api/merchant-portal/warehouse/inbound-requests/'.$receivedRequest->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Inbound stock request cannot be cancelled in its current status.');
    }

    public function test_capabilities_are_enforced_for_warehouse_views_inbound_requests_and_labels(): void
    {
        [$governorate, $area] = $this->makeLocation();
        $merchant = $this->makeMerchant();
        $warehouse = $this->makeWarehouse($governorate, $area);
        $product = $this->makeWarehouseProduct($merchant);
        $this->makeInventoryStock($warehouse, $product);
        $this->makeWarehouseCharge($merchant, $warehouse, $product);

        $warehouseViewer = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $warehouseViewer, [
            'role' => MerchantPortalRole::WAREHOUSE_VIEWER,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($warehouseViewer->fresh());
        $this->getJson('/api/merchant-portal/warehouse/products')->assertOk();
        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'items' => [[
                'warehouse_product_id' => $product->id,
                'quantity' => 5,
            ]],
        ])->assertStatus(403);
        $this->get('/api/merchant-portal/warehouse/products/'.$product->id.'/label?format=html')
            ->assertOk();

        $accountant = User::factory()->create(['role' => 'merchant_portal']);
        $this->makeMerchantPortalUser($merchant, $accountant, [
            'role' => MerchantPortalRole::ACCOUNTANT,
            'is_primary' => false,
        ]);

        Sanctum::actingAs($accountant->fresh());
        $this->getJson('/api/merchant-portal/warehouse/charges')->assertOk();
        $this->postJson('/api/merchant-portal/warehouse/inbound-requests', [
            'items' => [[
                'warehouse_product_id' => $product->id,
                'quantity' => 5,
            ]],
        ])->assertStatus(403);
        $this->get('/api/merchant-portal/warehouse/products/'.$product->id.'/label?format=html')
            ->assertStatus(403);
    }
}
