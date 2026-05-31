<?php

namespace Tests\Feature\Concerns;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Attachment\Models\Attachment;
use App\Modules\Driver\Models\Driver;
use App\Modules\Export\Models\ExportLog;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantPortal\Models\MerchantNotification;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Role;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseCharge;
use App\Modules\Warehouse\Models\WarehouseProduct;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Laravel\Sanctum\Sanctum;

trait InteractsWithPlatform
{
    protected function seedPlatform(): void
    {
        $this->seed([
            SystemSettingSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    protected function actingAsSuperAdmin(): User
    {
        return $this->actingAsRole('super_admin');
    }

    protected function actingAsRole(string $roleSlug): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $user = User::factory()->create();
        $legacyRole = $roleSlug === 'super_admin' ? 'admin' : $roleSlug;

        $user->update([
            'role_id' => $role->id,
            'role' => $legacyRole,
        ]);

        Sanctum::actingAs($user->fresh());

        return $user->fresh();
    }

    protected function makeMerchant(array $overrides = []): Merchant
    {
        $sequence = Merchant::query()->count() + 1;

        return Merchant::create(array_merge([
            'name' => 'Merchant '.$sequence,
            'company_name' => 'Merchant '.$sequence.' Co',
            'phone' => '0100000'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'email' => 'merchant'.$sequence.'@example.com',
            'address' => 'Cairo',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @return array{0: Governorate, 1: Area}
     */
    protected function makeLocation(string $governorateName = 'Cairo', string $areaName = 'Nasr City'): array
    {
        $governorate = Governorate::create([
            'name' => $governorateName.' '.(Governorate::query()->count() + 1),
            'is_active' => true,
        ]);

        $area = Area::create([
            'governorate_id' => $governorate->id,
            'name' => $areaName.' '.(Area::query()->count() + 1),
            'is_active' => true,
        ]);

        return [$governorate, $area];
    }

    protected function makeDriver(array $overrides = []): Driver
    {
        $sequence = Driver::query()->count() + 1;

        return Driver::create(array_merge([
            'name' => 'Driver '.$sequence,
            'phone' => '0110000'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'status' => 'active',
        ], $overrides));
    }

    protected function makeWarehouse(Governorate $governorate, Area $area, array $overrides = []): Warehouse
    {
        $sequence = Warehouse::query()->count() + 1;

        return Warehouse::create(array_merge([
            'name' => 'Warehouse '.$sequence,
            'code' => 'WH-'.$sequence,
            'address' => 'Warehouse Address',
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'status' => 'active',
        ], $overrides));
    }

    protected function makeWarehouseProduct(Merchant $merchant, array $overrides = []): WarehouseProduct
    {
        $sequence = WarehouseProduct::query()->count() + 1;

        return WarehouseProduct::create(array_merge([
            'merchant_id' => $merchant->id,
            'name' => 'Warehouse Product '.$sequence,
            'sku' => 'SKU-'.$sequence,
            'barcode' => 'WHP-'.$sequence,
            'status' => 'active',
            'minimum_stock_quantity' => 5,
            'low_stock_alert_enabled' => true,
        ], $overrides));
    }

    protected function makeInventoryStock(Warehouse $warehouse, WarehouseProduct $warehouseProduct, array $overrides = []): InventoryStock
    {
        return InventoryStock::create(array_merge([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct->id,
            'quantity_available' => 20,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
        ], $overrides));
    }

    protected function makeStockMovement(Warehouse $warehouse, WarehouseProduct $warehouseProduct, array $overrides = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct->id,
            'type' => 'stock_in',
            'quantity' => 10,
            'before_available' => 0,
            'after_available' => 10,
            'before_reserved' => 0,
            'after_reserved' => 0,
            'before_damaged' => 0,
            'after_damaged' => 0,
            'reference_type' => 'warehouse_receipt',
            'reference_id' => 1,
        ], $overrides));
    }

    protected function makeWarehouseCharge(Merchant $merchant, Warehouse $warehouse, ?WarehouseProduct $warehouseProduct = null, array $overrides = []): WarehouseCharge
    {
        return WarehouseCharge::create(array_merge([
            'merchant_id' => $merchant->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct?->id,
            'type' => 'storage',
            'description' => 'Storage fee',
            'quantity' => 1,
            'unit_price' => '25.00',
            'amount' => '25.00',
            'status' => 'pending',
            'charge_date' => now()->toDateString(),
        ], $overrides));
    }

    protected function makeTreasuryAccount(array $overrides = []): TreasuryAccount
    {
        $sequence = TreasuryAccount::query()->count() + 1;

        return TreasuryAccount::create(array_merge([
            'name' => 'Cashbox '.$sequence,
            'code' => 'CASH-'.$sequence,
            'type' => 'cash',
            'opening_balance' => '10000.00',
            'current_balance' => '10000.00',
            'status' => 'active',
        ], $overrides));
    }

    protected function makeMerchantWalletTransaction(Merchant $merchant, array $overrides = []): MerchantWalletTransaction
    {
        $sequence = MerchantWalletTransaction::query()->count() + 1;

        return MerchantWalletTransaction::create(array_merge([
            'merchant_id' => $merchant->id,
            'transaction_number' => 'MWT-TEST-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '100.00',
            'balance_after' => '100.00',
            'status' => 'approved',
            'reason' => 'Test wallet transaction',
        ], $overrides));
    }

    protected function makeMerchantInvoice(Merchant $merchant, array $overrides = [], array $items = []): MerchantInvoice
    {
        $sequence = MerchantInvoice::query()->count() + 1;

        $invoice = MerchantInvoice::create(array_merge([
            'merchant_id' => $merchant->id,
            'invoice_number' => 'INV-TEST-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_cod' => '500.00',
            'total_shipping_fees' => '50.00',
            'total_warehouse_charges' => '20.00',
            'total_payable' => '430.00',
            'status' => 'issued',
            'issued_at' => now(),
        ], $overrides));

        foreach ($items !== [] ? $items : [[
            'item_type' => 'shipping_fee',
            'description' => 'Shipping fee',
            'quantity' => 1,
            'unit_amount' => '50.00',
            'total_amount' => '50.00',
        ]] as $item) {
            $invoice->items()->create($item);
        }

        return $invoice->fresh('items');
    }

    protected function makeMerchantPayout(Merchant $merchant, array $overrides = []): MerchantPayout
    {
        return MerchantPayout::create(array_merge([
            'merchant_id' => $merchant->id,
            'amount' => '100.00',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'reference_number' => 'PAYOUT-TEST-'.str_pad((string) (MerchantPayout::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'notes' => 'Test payout',
        ], $overrides));
    }

    protected function makeMerchantIntegrationToken(Merchant $merchant, array $overrides = []): MerchantIntegrationToken
    {
        $sequence = MerchantIntegrationToken::query()->count() + 1;

        return MerchantIntegrationToken::create(array_merge([
            'merchant_id' => $merchant->id,
            'name' => 'Integration Token '.$sequence,
            'token_hash' => 'hash-'.$sequence,
            'token_prefix' => 'mit_live_prefix_'.$sequence,
            'provider' => MerchantIntegrationToken::PROVIDER_CUSTOM,
            'permissions' => MerchantIntegrationToken::DEFAULT_PERMISSIONS,
            'status' => MerchantIntegrationToken::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function makeMerchantPortalSetting(Merchant $merchant, array $overrides = []): MerchantPortalSetting
    {
        return MerchantPortalSetting::create(array_merge([
            'merchant_id' => $merchant->id,
            'default_payment_type' => 'cod',
            'default_pickup_address' => $merchant->address,
            'default_label_format' => 'html',
            'notification_preferences' => [],
            'integration_preferences' => [],
        ], $overrides));
    }

    protected function makeMerchantNotification(Merchant $merchant, array $overrides = []): MerchantNotification
    {
        $sequence = MerchantNotification::query()->count() + 1;

        return MerchantNotification::create(array_merge([
            'merchant_id' => $merchant->id,
            'type' => 'invoice_issued',
            'title' => 'Notification '.$sequence,
            'message' => 'Test notification '.$sequence,
            'data' => [],
        ], $overrides));
    }

    protected function makeExportLog(array $overrides = []): ExportLog
    {
        $sequence = ExportLog::query()->count() + 1;

        return ExportLog::create(array_merge([
            'merchant_id' => null,
            'export_type' => 'orders',
            'format' => 'csv',
            'filters' => null,
            'status' => 'completed',
            'file_path' => 'exports/export-'.$sequence.'.csv',
            'generated_at' => now(),
        ], $overrides));
    }

    protected function makeAttachment(Model $attachable, array $overrides = []): Attachment
    {
        $attachableType = array_search(get_class($attachable), config('attachments.attachable_types', []), true);
        $merchantId = $attachable->merchant_id ?? ($attachable instanceof Merchant ? $attachable->id : null);

        return Attachment::create(array_merge([
            'merchant_id' => $merchantId,
            'attachable_type' => is_string($attachableType) ? $attachableType : 'merchants',
            'attachable_id' => $attachable->id,
            'file_name' => 'file.pdf',
            'original_name' => 'file.pdf',
            'file_path' => 'attachments/test/file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'category' => 'support_document',
            'merchant_uploaded' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, array<string, mixed>>|null  $items
     */
    protected function makeOrder(
        Merchant $merchant,
        Governorate $governorate,
        Area $area,
        array $overrides = [],
        ?array $items = null,
    ): Order {
        $sequence = Order::query()->count() + 1;

        $order = Order::create(array_merge([
            'merchant_id' => $merchant->id,
            'order_number' => 'ORD-TEST-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'customer_name' => 'Customer '.$sequence,
            'customer_phone' => '0120000'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'Test Address '.$sequence,
            'cod_amount' => '100.00',
            'shipping_fee' => '20.00',
            'order_type' => 'delivery',
            'payment_type' => PaymentType::COD,
            'fulfillment_type' => OrderFulfillmentType::PICKUP_FROM_MERCHANT,
            'source' => 'manual',
            'status' => OrderStatus::CONFIRMED,
            'requires_review' => false,
        ], $overrides));

        $order->items()->createMany($items ?? [[
            'product_name' => 'Test Item',
            'quantity' => 1,
            'unit_price' => '100.00',
        ]]);

        return $order->fresh('items');
    }

    protected function makeShipment(Order $order, array $overrides = []): Shipment
    {
        return Shipment::create(array_merge([
            'order_id' => $order->id,
            'shipment_number' => 'SHP-TEST-'.str_pad((string) (Shipment::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'merchant_id' => $order->merchant_id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_governorate_id' => $order->delivery_governorate_id,
            'delivery_area_id' => $order->delivery_area_id,
            'delivery_address' => $order->delivery_address,
            'cod_amount' => $order->cod_amount,
            'shipping_fee' => $order->shipping_fee,
            'status' => 'pending_pickup',
        ], $overrides));
    }

    protected function makeMerchantPortalUser(
        Merchant $merchant,
        ?User $user = null,
        array $overrides = [],
    ): MerchantUser {
        $user ??= User::factory()->create([
            'role' => 'merchant_portal',
            'role_id' => Role::query()->where('slug', 'merchant_portal')->value('id'),
        ]);

        return MerchantUser::create(array_merge([
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            'role' => MerchantPortalRole::OWNER,
            'status' => MerchantPortalUserStatus::ACTIVE,
            'is_primary' => true,
            'accepted_at' => now(),
        ], $overrides));
    }
}
