<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use App\Support\DemoDataRegistry;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithPlatform;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use InteractsWithPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_demo_data_seeder_is_idempotent_and_creates_expected_records(): void
    {
        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class])->assertExitCode(0);
        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => DemoDataRegistry::INTERNAL_USER_EMAIL]);
        $this->assertDatabaseHas('users', ['email' => DemoDataRegistry::MERCHANT_PORTAL_USER_EMAIL]);
        $this->assertDatabaseHas('merchants', ['email' => DemoDataRegistry::MERCHANT_EMAIL]);
        $this->assertDatabaseHas('governorates', ['name' => DemoDataRegistry::GOVERNORATE_NAME]);
        $this->assertDatabaseHas('areas', ['name' => DemoDataRegistry::AREA_NAME]);
        $this->assertDatabaseHas('warehouses', ['code' => DemoDataRegistry::WAREHOUSE_CODE]);
        $this->assertDatabaseHas('warehouse_products', ['sku' => DemoDataRegistry::PRODUCT_SKU]);
        $this->assertDatabaseHas('orders', ['order_number' => DemoDataRegistry::ORDER_NUMBER]);
        $this->assertDatabaseHas('shipments', ['shipment_number' => DemoDataRegistry::SHIPMENT_NUMBER]);
        $this->assertDatabaseHas('merchant_wallet_transactions', ['transaction_number' => DemoDataRegistry::WALLET_TRANSACTION_NUMBER]);
        $this->assertDatabaseHas('merchant_invoices', ['invoice_number' => DemoDataRegistry::INVOICE_NUMBER]);
        $this->assertDatabaseHas('merchant_payouts', ['reference_number' => DemoDataRegistry::PAYOUT_REFERENCE]);

        $this->assertSame(2, User::query()->whereIn('email', DemoDataRegistry::userEmails())->count());
        $this->assertSame(1, Merchant::query()->where('email', DemoDataRegistry::MERCHANT_EMAIL)->count());
        $this->assertSame(1, Governorate::query()->where('name', DemoDataRegistry::GOVERNORATE_NAME)->count());
        $this->assertSame(1, Area::query()->where('name', DemoDataRegistry::AREA_NAME)->count());
        $this->assertSame(1, Warehouse::query()->where('code', DemoDataRegistry::WAREHOUSE_CODE)->count());
        $this->assertSame(1, WarehouseZone::query()->where('code', DemoDataRegistry::WAREHOUSE_ZONE_CODE)->count());
        $this->assertSame(1, WarehouseShelf::query()->where('code', DemoDataRegistry::WAREHOUSE_SHELF_CODE)->count());
        $this->assertSame(1, WarehouseBin::query()->where('code', DemoDataRegistry::WAREHOUSE_BIN_CODE)->count());
        $this->assertSame(1, Driver::query()->where('phone', DemoDataRegistry::DRIVER_PHONE)->count());
        $this->assertSame(1, WarehouseProduct::query()->where('sku', DemoDataRegistry::PRODUCT_SKU)->count());
        $this->assertSame(1, InventoryStock::query()->count());
        $this->assertSame(1, MerchantPortalSetting::query()->count());
        $this->assertSame(1, MerchantUser::query()->count());
        $this->assertSame(1, Order::query()->where('order_number', DemoDataRegistry::ORDER_NUMBER)->count());
        $this->assertSame(1, Shipment::query()->where('shipment_number', DemoDataRegistry::SHIPMENT_NUMBER)->count());
        $this->assertSame(3, ShipmentStatusHistory::query()->whereHas('shipment', function ($query): void {
            $query->where('shipment_number', DemoDataRegistry::SHIPMENT_NUMBER);
        })->count());
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'demo_seed')->count());
        $this->assertSame(1, MerchantWalletTransaction::query()->where('transaction_number', DemoDataRegistry::WALLET_TRANSACTION_NUMBER)->count());
        $this->assertSame(1, MerchantInvoice::query()->where('invoice_number', DemoDataRegistry::INVOICE_NUMBER)->count());
        $this->assertSame(1, MerchantPayout::query()->where('reference_number', DemoDataRegistry::PAYOUT_REFERENCE)->count());
    }

    public function test_demo_purge_command_requires_confirmation_when_not_forced(): void
    {
        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class])->assertExitCode(0);

        $this->artisan('demo:purge')
            ->expectsConfirmation(
                'This will permanently delete only records created by DemoDataSeeder. Continue?',
                'no'
            )
            ->expectsOutput('Demo data purge cancelled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('merchants', ['email' => DemoDataRegistry::MERCHANT_EMAIL]);
        $this->assertDatabaseHas('orders', ['order_number' => DemoDataRegistry::ORDER_NUMBER]);
        $this->assertDatabaseHas('shipments', ['shipment_number' => DemoDataRegistry::SHIPMENT_NUMBER]);
    }

    public function test_demo_purge_command_removes_only_demo_records(): void
    {
        $realUser = User::factory()->create([
            'email' => 'real.user@example.com',
            'role' => 'admin',
        ]);
        $realMerchant = $this->makeMerchant([
            'email' => 'real-merchant@example.com',
            'name' => 'Real Merchant',
        ]);
        [$realGovernorate, $realArea] = $this->makeLocation('Real Governorate', 'Real Area');
        $realWarehouse = $this->makeWarehouse($realGovernorate, $realArea, [
            'code' => 'REAL-WH-01',
        ]);
        $realProduct = $this->makeWarehouseProduct($realMerchant, [
            'sku' => 'REAL-SKU-01',
        ]);
        $this->makeInventoryStock($realWarehouse, $realProduct, [
            'quantity_available' => 15,
        ]);
        $realOrder = $this->makeOrder($realMerchant, $realGovernorate, $realArea, [
            'order_number' => 'REAL-ORDER-0001',
        ]);
        $this->makeShipment($realOrder, [
            'shipment_number' => 'REAL-SHIP-0001',
        ]);

        $this->artisan('db:seed', ['--class' => DemoDataSeeder::class])->assertExitCode(0);

        $this->artisan('demo:purge', ['--force' => true])
            ->expectsOutput('Demo data purge completed.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => DemoDataRegistry::INTERNAL_USER_EMAIL]);
        $this->assertDatabaseMissing('users', ['email' => DemoDataRegistry::MERCHANT_PORTAL_USER_EMAIL]);
        $this->assertDatabaseMissing('merchants', ['email' => DemoDataRegistry::MERCHANT_EMAIL]);
        $this->assertDatabaseMissing('governorates', ['name' => DemoDataRegistry::GOVERNORATE_NAME]);
        $this->assertDatabaseMissing('areas', ['name' => DemoDataRegistry::AREA_NAME]);
        $this->assertDatabaseMissing('warehouses', ['code' => DemoDataRegistry::WAREHOUSE_CODE]);
        $this->assertDatabaseMissing('warehouse_products', ['sku' => DemoDataRegistry::PRODUCT_SKU]);
        $this->assertDatabaseMissing('orders', ['order_number' => DemoDataRegistry::ORDER_NUMBER]);
        $this->assertDatabaseMissing('shipments', ['shipment_number' => DemoDataRegistry::SHIPMENT_NUMBER]);
        $this->assertDatabaseMissing('merchant_wallet_transactions', ['transaction_number' => DemoDataRegistry::WALLET_TRANSACTION_NUMBER]);
        $this->assertDatabaseMissing('merchant_invoices', ['invoice_number' => DemoDataRegistry::INVOICE_NUMBER]);
        $this->assertDatabaseMissing('merchant_payouts', ['reference_number' => DemoDataRegistry::PAYOUT_REFERENCE]);

        $this->assertDatabaseHas('users', ['email' => $realUser->email]);
        $this->assertDatabaseHas('merchants', ['email' => $realMerchant->email]);
        $this->assertDatabaseHas('governorates', ['id' => $realGovernorate->id]);
        $this->assertDatabaseHas('areas', ['id' => $realArea->id]);
        $this->assertDatabaseHas('warehouses', ['id' => $realWarehouse->id]);
        $this->assertDatabaseHas('warehouse_products', ['id' => $realProduct->id]);
        $this->assertDatabaseHas('orders', ['order_number' => 'REAL-ORDER-0001']);
        $this->assertDatabaseHas('shipments', ['shipment_number' => 'REAL-SHIP-0001']);
    }
}
