<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
use App\Support\DemoDataRegistry;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantPortalSetting;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\RolePermission\Models\Role;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $admin = $this->upsertSoftDeletedModel(User::class, [
            'email' => DemoDataRegistry::INTERNAL_USER_EMAIL,
        ], [
            'name' => 'DEMO Admin User',
            'password' => DemoDataRegistry::PASSWORD,
            'role' => 'admin',
            'role_id' => Role::query()->where('slug', 'admin')->value('id'),
        ]);

        $merchantPortalUser = $this->upsertSoftDeletedModel(User::class, [
            'email' => DemoDataRegistry::MERCHANT_PORTAL_USER_EMAIL,
        ], [
            'name' => 'DEMO Merchant Portal User',
            'password' => DemoDataRegistry::PASSWORD,
            'role' => 'merchant_portal',
            'role_id' => Role::query()->where('slug', 'merchant_portal')->value('id'),
        ]);

        $merchant = $this->upsertSoftDeletedModel(Merchant::class, [
            'email' => DemoDataRegistry::MERCHANT_EMAIL,
        ], [
            'name' => 'DEMO Merchant',
            'company_name' => 'DEMO Merchant Logistics Store',
            'phone' => '01010000001',
            'address' => 'DEMO Merchant HQ, Cairo',
            'status' => 'active',
        ]);

        $governorate = $this->upsertSoftDeletedModel(Governorate::class, [
            'name' => DemoDataRegistry::GOVERNORATE_NAME,
        ], [
            'is_active' => true,
        ]);

        $area = $this->upsertSoftDeletedModel(Area::class, [
            'governorate_id' => $governorate->id,
            'name' => DemoDataRegistry::AREA_NAME,
        ], [
            'is_active' => true,
        ]);

        $warehouse = $this->upsertSoftDeletedModel(Warehouse::class, [
            'code' => DemoDataRegistry::WAREHOUSE_CODE,
        ], [
            'name' => 'DEMO Main Warehouse',
            'address' => 'DEMO Warehouse District',
            'governorate_id' => $governorate->id,
            'area_id' => $area->id,
            'status' => 'active',
            'notes' => DemoDataRegistry::note('Demo warehouse seeded for removability checks.'),
        ]);

        $zone = $this->upsertSoftDeletedModel(WarehouseZone::class, [
            'warehouse_id' => $warehouse->id,
            'code' => DemoDataRegistry::WAREHOUSE_ZONE_CODE,
        ], [
            'name' => 'DEMO Zone A',
            'status' => 'active',
            'notes' => DemoDataRegistry::note('Demo warehouse zone.'),
        ]);

        $shelf = $this->upsertSoftDeletedModel(WarehouseShelf::class, [
            'warehouse_id' => $warehouse->id,
            'code' => DemoDataRegistry::WAREHOUSE_SHELF_CODE,
        ], [
            'warehouse_zone_id' => $zone->id,
            'name' => 'DEMO Shelf 01',
            'status' => 'active',
            'notes' => DemoDataRegistry::note('Demo warehouse shelf.'),
        ]);

        $bin = $this->upsertSoftDeletedModel(WarehouseBin::class, [
            'warehouse_id' => $warehouse->id,
            'code' => DemoDataRegistry::WAREHOUSE_BIN_CODE,
        ], [
            'warehouse_zone_id' => $zone->id,
            'warehouse_shelf_id' => $shelf->id,
            'name' => 'DEMO Bin 01',
            'barcode' => DemoDataRegistry::WAREHOUSE_BIN_BARCODE,
            'status' => 'active',
            'notes' => DemoDataRegistry::note('Demo warehouse bin.'),
        ]);

        $driver = $this->upsertSoftDeletedModel(Driver::class, [
            'phone' => DemoDataRegistry::DRIVER_PHONE,
        ], [
            'name' => 'DEMO Driver',
            'national_id' => 'DEMO-DRIVER-ID-01',
            'vehicle_type' => 'van',
            'vehicle_plate' => 'DEMO-PLATE-01',
            'status' => 'active',
            'notes' => DemoDataRegistry::note('Demo driver for seeded shipment assignments.'),
        ]);

        $warehouseProduct = $this->upsertSoftDeletedModel(WarehouseProduct::class, [
            'merchant_id' => $merchant->id,
            'sku' => DemoDataRegistry::PRODUCT_SKU,
        ], [
            'name' => 'DEMO Fulfillment Product',
            'barcode' => DemoDataRegistry::PRODUCT_BARCODE,
            'description' => DemoDataRegistry::note('Warehouse demo product for sample fulfillment flows.'),
            'status' => 'active',
            'minimum_stock_quantity' => 5,
            'low_stock_alert_enabled' => true,
            'notes' => DemoDataRegistry::note('Demo warehouse product.'),
        ]);

        InventoryStock::query()->updateOrCreate([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct->id,
        ], [
            'warehouse_bin_id' => $bin->id,
            'quantity_available' => 48,
            'quantity_reserved' => 2,
            'quantity_damaged' => 0,
        ]);

        MerchantPortalSetting::query()->updateOrCreate([
            'merchant_id' => $merchant->id,
        ], [
            'default_fulfillment_type' => OrderFulfillmentType::FROM_WAREHOUSE,
            'default_payment_type' => PaymentType::COD,
            'default_pickup_address' => 'DEMO Pickup Counter',
            'default_label_format' => 'html',
            'notification_preferences' => [
                'order_updates' => true,
                'invoice_updates' => true,
            ],
            'integration_preferences' => [
                'demo_mode' => true,
            ],
        ]);

        $this->upsertSoftDeletedModel(MerchantUser::class, [
            'merchant_id' => $merchant->id,
            'user_id' => $merchantPortalUser->id,
        ], [
            'role' => MerchantPortalRole::OWNER,
            'status' => MerchantPortalUserStatus::ACTIVE,
            'is_primary' => true,
            'invited_by' => $admin->id,
            'invited_at' => $now->copy()->subDays(3),
            'accepted_at' => $now->copy()->subDays(2),
            'last_login_at' => $now->copy()->subHour(),
        ]);

        $order = $this->upsertSoftDeletedModel(Order::class, [
            'order_number' => DemoDataRegistry::ORDER_NUMBER,
        ], [
            'merchant_id' => $merchant->id,
            'customer_name' => 'DEMO Customer',
            'customer_phone' => '01210000001',
            'customer_phone_alt' => '01210000002',
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => 'DEMO Customer Address',
            'delivery_notes' => 'Call before delivery.',
            'cod_amount' => '450.00',
            'shipping_fee' => '55.00',
            'order_type' => 'delivery',
            'payment_type' => PaymentType::COD,
            'fulfillment_type' => OrderFulfillmentType::FROM_WAREHOUSE,
            'source' => 'manual',
            'status' => OrderStatus::SHIPMENT_CREATED,
            'requires_review' => false,
            'notes' => DemoDataRegistry::note('Demo order for seeded dashboards and APIs.'),
            'created_by' => $admin->id,
        ]);

        $order->items()->delete();
        $order->items()->create([
            'product_name' => 'DEMO Fulfillment Product',
            'sku' => DemoDataRegistry::PRODUCT_SKU,
            'quantity' => 2,
            'delivery_status' => 'pending',
            'unit_price' => '225.00',
            'weight' => '0.50',
            'notes' => DemoDataRegistry::note('Demo order item.'),
        ]);

        $shipment = $this->upsertSoftDeletedModel(Shipment::class, [
            'shipment_number' => DemoDataRegistry::SHIPMENT_NUMBER,
        ], [
            'order_id' => $order->id,
            'merchant_id' => $merchant->id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_governorate_id' => $governorate->id,
            'delivery_area_id' => $area->id,
            'delivery_address' => $order->delivery_address,
            'cod_amount' => $order->cod_amount,
            'shipping_fee' => $order->shipping_fee,
            'status' => ShipmentStatus::IN_TRANSIT,
            'tracking_notes' => DemoDataRegistry::note('Internal demo-only tracking note.'),
            'created_by' => $admin->id,
            'assigned_driver_id' => $driver->id,
        ]);

        ShipmentStatusHistory::query()->where('shipment_id', $shipment->id)->delete();
        foreach ([
            [ShipmentStatus::PENDING_PICKUP, $now->copy()->subDays(2)],
            [ShipmentStatus::OUT_FOR_DELIVERY, $now->copy()->subDay()],
            [ShipmentStatus::IN_TRANSIT, $now->copy()->subHours(3)],
        ] as [$status, $timestamp]) {
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'notes' => DemoDataRegistry::note('Demo shipment timeline event: '.$status),
                'changed_by' => $admin->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        StockMovement::query()->where([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct->id,
            'reference_type' => 'demo_seed',
            'reference_id' => $order->id,
        ])->delete();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'warehouse_product_id' => $warehouseProduct->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'type' => 'reserved',
            'quantity' => 2,
            'before_available' => 50,
            'after_available' => 48,
            'before_reserved' => 0,
            'after_reserved' => 2,
            'before_damaged' => 0,
            'after_damaged' => 0,
            'reference_type' => 'demo_seed',
            'reference_id' => $order->id,
            'notes' => DemoDataRegistry::note('Demo stock reservation movement.'),
            'created_by' => $admin->id,
        ]);

        $this->upsertSoftDeletedModel(MerchantWalletTransaction::class, [
            'transaction_number' => DemoDataRegistry::WALLET_TRANSACTION_NUMBER,
        ], [
            'merchant_id' => $merchant->id,
            'transaction_type' => 'cod_collected',
            'direction' => 'credit',
            'amount' => '450.00',
            'balance_after' => '450.00',
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => $now->copy()->subHours(2),
            'reason' => 'Demo COD settlement',
            'notes' => DemoDataRegistry::note('Demo merchant wallet transaction.'),
            'reference_number' => DemoDataRegistry::SHIPMENT_NUMBER,
            'created_by' => $admin->id,
        ]);

        $this->upsertSoftDeletedModel(MerchantInvoice::class, [
            'invoice_number' => DemoDataRegistry::INVOICE_NUMBER,
        ], [
            'merchant_id' => $merchant->id,
            'period_start' => $now->copy()->startOfMonth()->toDateString(),
            'period_end' => $now->copy()->endOfMonth()->toDateString(),
            'total_cod' => '450.00',
            'total_shipping_fees' => '55.00',
            'total_payable' => '395.00',
            'status' => 'issued',
            'notes' => DemoDataRegistry::note('Demo merchant invoice.'),
            'created_by' => $admin->id,
            'issued_at' => $now->copy()->subHour(),
        ]);

        $this->upsertSoftDeletedModel(MerchantPayout::class, [
            'merchant_id' => $merchant->id,
            'reference_number' => DemoDataRegistry::PAYOUT_REFERENCE,
        ], [
            'amount' => '395.00',
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'notes' => DemoDataRegistry::note('Demo merchant payout request.'),
            'created_by' => $admin->id,
        ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    private function upsertSoftDeletedModel(string $modelClass, array $attributes, array $values): Model
    {
        /** @var Model $model */
        $model = $modelClass::withTrashed()->firstOrNew($attributes);
        $model->fill($values);

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $model->restore();
        }

        $model->save();

        return $model->fresh() ?? $model;
    }
}
