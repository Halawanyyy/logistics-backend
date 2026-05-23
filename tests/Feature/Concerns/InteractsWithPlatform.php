<?php

namespace Tests\Feature\Concerns;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\RolePermission\Models\Role;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\Warehouse;
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
}
