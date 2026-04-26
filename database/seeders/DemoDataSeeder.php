<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Driver\Enums\DriverStatus;
use App\Modules\Driver\Models\Driver;
use App\Modules\Finance\Enums\DriverCashClosureStatus;
use App\Modules\Finance\Enums\ExpenseCategory;
use App\Modules\Finance\Enums\MerchantInvoiceStatus;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Models\DriverCashClosure;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\LocationPricing\Models\ShippingRate;
use App\Modules\Order\Enums\OrderFulfillmentType;
use App\Modules\Order\Enums\OrderSource;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Shipment\Models\ShipmentStatusHistory;
use App\Modules\Warehouse\Enums\OrderStockReservationStatus;
use App\Modules\Warehouse\Enums\StockMovementType;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Enums\WarehouseProductStatus;
use App\Modules\Warehouse\Enums\WarehouseReturnCondition;
use App\Modules\Warehouse\Enums\WarehouseStatus;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\OrderStockReservation;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseCharge;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseReturn;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    /**
     * @var array<string, Governorate>
     */
    private array $governorates = [];

    /**
     * @var array<string, Area>
     */
    private array $areas = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedUsers();
            $merchants = $this->seedMerchants();
            $this->seedLocations();
            $drivers = $this->seedDrivers();
            $orders = $this->seedOrders($merchants);
            $shipments = $this->seedShipments($orders, $drivers);
            $this->seedWarehouse($merchants, $orders, $shipments);
            $this->seedFinance($merchants, $drivers);
        });
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['name' => 'Operations Staff', 'email' => 'staff@example.com', 'role' => 'staff'],
            ['name' => 'Warehouse Manager', 'email' => 'warehouse@example.com', 'role' => 'warehouse'],
            ['name' => 'Finance Officer', 'email' => 'finance@example.com', 'role' => 'finance'],
            ['name' => 'Driver Portal User', 'email' => 'driver.user@example.com', 'role' => 'driver'],
        ];

        foreach ($users as $data) {
            $this->users[$data['email']] = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('secret123'),
                    'role' => $data['role'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }

    /**
     * @return Collection<int, Merchant>
     */
    private function seedMerchants()
    {
        $merchants = [
            ['Ahmed Hassan', 'Nile Gadgets', '+201001234501', 'nile.gadgets@example.com', 'Nasr City, Cairo', 'active'],
            ['Mona Adel', 'Cairo Home Store', '+201001234502', 'cairo.home@example.com', 'Maadi, Cairo', 'active'],
            ['Karim Samir', 'Alex Fashion Hub', '+201001234503', 'alex.fashion@example.com', 'Smouha, Alexandria', 'active'],
            ['Sara Nabil', 'Giza Beauty Supply', '+201001234504', 'giza.beauty@example.com', 'Dokki, Giza', 'active'],
            ['Omar Mostafa', 'Delta Electronics', '+201001234505', 'delta.electronics@example.com', 'Tanta, Gharbia', 'active'],
            ['Yasmine Fathy', 'Qalyubia Kids Wear', '+201001234506', 'qalyubia.kids@example.com', 'Banha, Qalyubia', 'active'],
            ['Hany Saleh', 'Metro Books', '+201001234507', 'metro.books@example.com', 'Heliopolis, Cairo', 'active'],
            ['Nour Khaled', 'Urban Kitchenware', '+201001234508', 'urban.kitchen@example.com', 'Mohandessin, Giza', 'inactive'],
            ['Tamer Amin', 'Mediterranean Gifts', '+201001234509', 'med.gifts@example.com', 'Stanley, Alexandria', 'inactive'],
            ['Laila Youssef', 'Fresh Market Boxes', '+201001234510', 'fresh.boxes@example.com', 'Mahalla, Gharbia', 'active'],
        ];

        foreach ($merchants as [$name, $company, $phone, $email, $address, $status]) {
            Merchant::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'company_name' => $company,
                    'phone' => $phone,
                    'address' => $address,
                    'status' => $status,
                ],
            );
        }

        return Merchant::query()->whereIn('email', array_column($merchants, 3))->get();
    }

    private function seedLocations(): void
    {
        $locations = [
            'Cairo' => ['fee' => 60, 'areas' => ['Nasr City' => 70, 'Maadi' => 75, 'Heliopolis' => null, 'New Cairo' => 85]],
            'Giza' => ['fee' => 65, 'areas' => ['Dokki' => null, 'Mohandessin' => 75, '6th of October' => 90, 'Haram' => null]],
            'Alexandria' => ['fee' => 80, 'areas' => ['Smouha' => 90, 'Stanley' => null, 'Sidi Gaber' => null, 'Borg El Arab' => 110]],
            'Qalyubia' => ['fee' => 70, 'areas' => ['Banha' => null, 'Shubra El Kheima' => 80, 'Qalyub' => null]],
            'Gharbia' => ['fee' => 85, 'areas' => ['Tanta' => null, 'Mahalla' => 95, 'Kafr El Zayat' => null]],
        ];

        foreach ($locations as $governorateName => $config) {
            $governorate = Governorate::query()->updateOrCreate(
                ['name' => $governorateName],
                ['is_active' => true],
            );

            $this->governorates[$governorateName] = $governorate;

            ShippingRate::query()->updateOrCreate(
                ['governorate_id' => $governorate->id, 'area_id' => null],
                ['shipping_fee' => $config['fee'], 'is_active' => true],
            );

            foreach ($config['areas'] as $areaName => $fee) {
                $area = Area::query()->updateOrCreate(
                    ['governorate_id' => $governorate->id, 'name' => $areaName],
                    ['is_active' => true],
                );

                $this->areas[$governorateName.'|'.$areaName] = $area;

                if ($fee !== null) {
                    ShippingRate::query()->updateOrCreate(
                        ['governorate_id' => $governorate->id, 'area_id' => $area->id],
                        ['shipping_fee' => $fee, 'is_active' => true],
                    );
                }
            }
        }
    }

    /**
     * @return Collection<int, Driver>
     */
    private function seedDrivers()
    {
        $driverUser = $this->users['driver.user@example.com'] ?? null;
        $drivers = [
            ['Mahmoud Hassan', '+201101234501', '29801010100001', 'Motorcycle', 'ABC-4217', DriverStatus::ACTIVE, $driverUser?->id],
            ['Ibrahim Nasser', '+201101234502', '29502020200002', 'Van', 'VAN-8031', DriverStatus::ACTIVE, null],
            ['Mostafa Said', '+201101234503', '29103030300003', 'Car', 'CAR-2190', DriverStatus::ACTIVE, null],
            ['Youssef Gamal', '+201101234504', '29904040400004', 'Motorcycle', 'MTR-7782', DriverStatus::ACTIVE, null],
            ['Ali Reda', '+201101234505', '29405050500005', 'Van', 'VAN-6620', DriverStatus::ACTIVE, null],
            ['Khaled Tarek', '+201101234506', '29206060600006', 'Car', 'CAR-5509', DriverStatus::ACTIVE, null],
            ['Samir Farid', '+201101234507', '29007070700007', 'Motorcycle', 'MTR-3125', DriverStatus::INACTIVE, null],
            ['Hossam Adel', '+201101234508', '29308080800008', 'Van', 'VAN-1470', DriverStatus::INACTIVE, null],
        ];

        foreach ($drivers as [$name, $phone, $nationalId, $vehicleType, $vehiclePlate, $status, $userId]) {
            Driver::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'user_id' => $userId,
                    'name' => $name,
                    'national_id' => $nationalId,
                    'vehicle_type' => $vehicleType,
                    'vehicle_plate' => $vehiclePlate,
                    'status' => $status,
                    'notes' => $status === DriverStatus::ACTIVE ? 'Demo active fleet driver.' : 'Demo inactive driver.',
                ],
            );
        }

        return Driver::query()->whereIn('phone', array_column($drivers, 1))->get();
    }

    /**
     * @param  Collection<int, Merchant>  $merchants
     * @return Collection<int, Order>
     */
    private function seedOrders($merchants)
    {
        $products = [
            ['Wireless Earbuds', 'AUD-EARBUDS', 850, 0.3],
            ['Cotton T-Shirt', 'APP-TSHIRT', 260, 0.25],
            ['Ceramic Mug Set', 'HOME-MUG', 420, 1.2],
            ['Phone Case', 'MOB-CASE', 180, 0.15],
            ['Desk Lamp', 'HOME-LAMP', 690, 1.4],
            ['Kids Backpack', 'KIDS-BAG', 540, 0.8],
            ['Running Shoes', 'APP-SHOES', 1150, 1.1],
            ['Skincare Box', 'BEAUTY-BOX', 730, 0.6],
        ];
        $locationPairs = array_values($this->areas);
        $statuses = array_merge(
            array_fill(0, 45, OrderStatus::CONFIRMED),
            array_fill(0, 7, OrderStatus::DRAFT),
            array_fill(0, 8, OrderStatus::PENDING_REVIEW),
            array_fill(0, 5, OrderStatus::CANCELLED),
        );

        foreach ($statuses as $index => $status) {
            $sequence = 900001 + $index;
            $createdAt = now()->subDays($index % 30)->setTime(10 + ($index % 8), 0, 0);
            $merchant = $merchants[$index % $merchants->count()];
            $area = $locationPairs[$index % count($locationPairs)];
            $governorate = $area->governorate;
            $requiresReview = $status === OrderStatus::PENDING_REVIEW;
            $paymentType = $index % 4 === 0 ? PaymentType::PREPAID : PaymentType::COD;
            $itemA = $products[$index % count($products)];
            $itemB = $products[($index + 3) % count($products)];
            $quantityA = ($index % 3) + 1;
            $quantityB = $index % 5 === 0 ? 2 : 1;
            $itemsTotal = ($itemA[2] * $quantityA) + ($itemB[2] * $quantityB);
            $shippingFee = $requiresReview ? 0 : $this->shippingFeeFor($governorate->id, $area->id);
            $codAmount = $paymentType === PaymentType::COD ? $itemsTotal + $shippingFee : 0;

            $order = Order::query()->firstOrCreate(
                ['order_number' => 'ORD-'.now()->format('Ymd').'-'.$sequence],
                [
                    'merchant_id' => $merchant->id,
                    'customer_name' => $this->customerName($index),
                    'customer_phone' => '010'.str_pad((string) (50000000 + $index), 8, '0', STR_PAD_LEFT),
                    'customer_phone_alt' => $index % 6 === 0 ? '012'.str_pad((string) (60000000 + $index), 8, '0', STR_PAD_LEFT) : null,
                    'delivery_governorate_id' => $governorate->id,
                    'delivery_area_id' => $area->id,
                    'delivery_address' => (($index % 40) + 1).' Demo Street, '.$area->name,
                    'delivery_notes' => $index % 4 === 0 ? 'Call before delivery.' : null,
                    'pickup_governorate_id' => $this->governorates['Cairo']->id,
                    'pickup_area_id' => $this->areas['Cairo|Nasr City']->id,
                    'pickup_address' => 'Demo merchant pickup desk, Nasr City',
                    'pickup_notes' => $index % 7 === 0 ? 'Pickup after 2 PM.' : null,
                    'cod_amount' => $codAmount,
                    'shipping_fee' => $shippingFee,
                    'payment_type' => $paymentType,
                    'fulfillment_type' => $index % 3 === 0 ? OrderFulfillmentType::FROM_WAREHOUSE : OrderFulfillmentType::PICKUP_FROM_MERCHANT,
                    'is_fragile' => $index % 6 === 0,
                    'allow_inspection' => $index % 2 === 0,
                    'requires_packaging' => $index % 5 === 0,
                    'package_notes' => $index % 5 === 0 ? 'Use branded packaging.' : null,
                    'source' => $index % 9 === 0 ? OrderSource::INTEGRATION : OrderSource::MANUAL,
                    'external_source' => $index % 9 === 0 ? 'shopify_demo' : null,
                    'external_order_id' => $index % 9 === 0 ? 'shopify-'.$sequence : null,
                    'external_order_number' => $index % 9 === 0 ? '#'.$sequence : null,
                    'requires_review' => $requiresReview,
                    'review_reason' => $requiresReview ? 'Demo order intentionally flagged for pricing or address review.' : null,
                    'status' => $status,
                    'notes' => $status === OrderStatus::CANCELLED ? 'Customer cancelled before dispatch.' : null,
                    'created_by' => $this->users['staff@example.com']->id,
                ],
            );
            $this->touch($order, $createdAt);

            if ($order->items()->count() === 0) {
                $order->items()->create([
                    'product_name' => $itemA[0],
                    'sku' => $itemA[1].'-'.($index + 1),
                    'quantity' => $quantityA,
                    'unit_price' => $itemA[2],
                    'weight' => $itemA[3],
                    'notes' => null,
                ]);
                $order->items()->create([
                    'product_name' => $itemB[0],
                    'sku' => $itemB[1].'-'.($index + 1),
                    'quantity' => $quantityB,
                    'unit_price' => $itemB[2],
                    'weight' => $itemB[3],
                    'notes' => $index % 5 === 0 ? 'Bundle item.' : null,
                ]);
            }
        }

        return Order::query()->where('order_number', 'like', 'ORD-'.now()->format('Ymd').'-9%')->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, Driver>  $drivers
     * @return Collection<int, Shipment>
     */
    private function seedShipments($orders, $drivers)
    {
        $activeDrivers = $drivers->where('status', DriverStatus::ACTIVE)->values();
        $statuses = [
            ShipmentStatus::PENDING_PICKUP,
            ShipmentStatus::PICKED_UP,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::OUT_FOR_DELIVERY,
            ShipmentStatus::DELIVERED,
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
            ShipmentStatus::CANCELLED,
        ];

        $confirmedOrders = $orders
            ->where('status', OrderStatus::CONFIRMED)
            ->where('requires_review', false)
            ->take(45)
            ->values();

        foreach ($confirmedOrders as $index => $order) {
            $status = $statuses[$index % count($statuses)];
            $createdAt = Carbon::parse($order->created_at)->addHours(3);
            $assignedDriverId = $index % 5 === 0 ? null : $activeDrivers[$index % $activeDrivers->count()]->id;
            $shipment = Shipment::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'shipment_number' => 'SHP-'.now()->format('Ymd').'-'.(900001 + $index),
                    'merchant_id' => $order->merchant_id,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'delivery_governorate_id' => $order->delivery_governorate_id,
                    'delivery_area_id' => $order->delivery_area_id,
                    'delivery_address' => $order->delivery_address,
                    'cod_amount' => $order->cod_amount,
                    'shipping_fee' => $order->shipping_fee,
                    'status' => $status,
                    'tracking_notes' => $this->trackingNotes($status),
                    'created_by' => $this->users['staff@example.com']->id,
                    'assigned_driver_id' => $assignedDriverId,
                ],
            );
            $shipment->forceFill([
                'status' => $status,
                'tracking_notes' => $this->trackingNotes($status),
                'assigned_driver_id' => $assignedDriverId,
            ])->save();
            $this->touch($shipment, $createdAt);
            $this->seedShipmentHistories($shipment, $status, $createdAt);
        }

        return Shipment::query()->where('shipment_number', 'like', 'SHP-'.now()->format('Ymd').'-9%')->get();
    }

    /**
     * @param  Collection<int, Merchant>  $merchants
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, Shipment>  $shipments
     */
    private function seedWarehouse($merchants, $orders, $shipments): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'WH-CAI-01'],
            [
                'name' => 'Cairo Main Warehouse',
                'address' => 'Industrial Zone, Nasr City, Cairo',
                'governorate_id' => $this->governorates['Cairo']->id,
                'area_id' => $this->areas['Cairo|Nasr City']->id,
                'status' => WarehouseStatus::ACTIVE,
                'notes' => 'Primary demo fulfillment warehouse.',
            ],
        );

        Warehouse::query()->updateOrCreate(
            ['code' => 'WH-GIZ-01'],
            [
                'name' => 'Giza Overflow Warehouse',
                'address' => 'Logistics Park, 6th of October, Giza',
                'governorate_id' => $this->governorates['Giza']->id,
                'area_id' => $this->areas['Giza|6th of October']->id,
                'status' => WarehouseStatus::ACTIVE,
                'notes' => 'Secondary demo storage location.',
            ],
        );

        $productNames = ['Blue T-Shirt', 'Wireless Earbuds', 'Ceramic Mug Set', 'Phone Case', 'Kids Backpack', 'Desk Lamp'];
        foreach ($merchants->take(6) as $merchantIndex => $merchant) {
            foreach ($productNames as $productIndex => $name) {
                $product = WarehouseProduct::query()->firstOrCreate(
                    ['merchant_id' => $merchant->id, 'sku' => 'DEMO-M'.$merchant->id.'-P'.($productIndex + 1)],
                    [
                        'name' => $name,
                        'barcode' => '622'.str_pad((string) ($merchant->id * 1000 + $productIndex), 10, '0', STR_PAD_LEFT),
                        'description' => 'Demo warehouse product for '.$merchant->company_name,
                        'unit_weight' => [0.25, 0.35, 1.20, 0.15, 0.80, 1.40][$productIndex],
                        'unit_length' => 20,
                        'unit_width' => 15,
                        'unit_height' => 8,
                        'is_fragile' => in_array($name, ['Wireless Earbuds', 'Ceramic Mug Set', 'Desk Lamp'], true),
                        'requires_packaging' => true,
                        'status' => WarehouseProductStatus::ACTIVE,
                        'notes' => null,
                    ],
                );

                $available = 40 + ($merchantIndex * 5) + ($productIndex * 3);
                $damaged = $productIndex % 5 === 0 ? 2 : 0;
                $stock = InventoryStock::query()->updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'warehouse_product_id' => $product->id],
                    [
                        'quantity_available' => $available,
                        'quantity_reserved' => 0,
                        'quantity_damaged' => $damaged,
                    ],
                );

                StockMovement::query()->firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'warehouse_product_id' => $product->id,
                        'type' => StockMovementType::IN,
                        'reference_type' => 'demo_initial_stock',
                    ],
                    [
                        'quantity' => $available + $damaged,
                        'before_available' => 0,
                        'after_available' => $available,
                        'before_reserved' => 0,
                        'after_reserved' => 0,
                        'before_damaged' => 0,
                        'after_damaged' => $damaged,
                        'notes' => 'Initial demo stock intake.',
                        'created_by' => $this->users['warehouse@example.com']->id,
                    ],
                );
            }
        }

        $warehouseOrders = $orders
            ->where('status', OrderStatus::CONFIRMED)
            ->where('fulfillment_type', OrderFulfillmentType::FROM_WAREHOUSE)
            ->take(8);

        foreach ($warehouseOrders as $index => $order) {
            $product = WarehouseProduct::query()->where('merchant_id', $order->merchant_id)->first();
            $orderItem = $order->items()->first();

            if (! $product || ! $orderItem) {
                continue;
            }

            $stock = InventoryStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('warehouse_product_id', $product->id)
                ->first();

            if (! $stock || $stock->quantity_available < 2) {
                continue;
            }

            $quantity = 1 + ($index % 2);
            $beforeAvailable = $stock->quantity_available;
            $beforeReserved = $stock->quantity_reserved;
            $stock->forceFill([
                'quantity_available' => $beforeAvailable - $quantity,
                'quantity_reserved' => $beforeReserved + $quantity,
            ])->save();

            $reservation = OrderStockReservation::query()->firstOrCreate(
                ['order_id' => $order->id, 'warehouse_product_id' => $product->id],
                [
                    'order_item_id' => $orderItem->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $quantity,
                    'status' => OrderStockReservationStatus::RESERVED,
                    'notes' => 'Demo warehouse reservation.',
                    'created_by' => $this->users['warehouse@example.com']->id,
                ],
            );

            StockMovement::query()->firstOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_product_id' => $product->id,
                    'type' => StockMovementType::RESERVED,
                    'reference_type' => OrderStockReservation::class,
                    'reference_id' => $reservation->id,
                ],
                [
                    'quantity' => $quantity,
                    'before_available' => $beforeAvailable,
                    'after_available' => $beforeAvailable - $quantity,
                    'before_reserved' => $beforeReserved,
                    'after_reserved' => $beforeReserved + $quantity,
                    'before_damaged' => $stock->quantity_damaged,
                    'after_damaged' => $stock->quantity_damaged,
                    'notes' => 'Reserved for demo warehouse order.',
                    'created_by' => $this->users['warehouse@example.com']->id,
                ],
            );
        }

        foreach ($shipments->whereIn('status', [ShipmentStatus::RETURNED, ShipmentStatus::FAILED])->take(4) as $shipment) {
            $product = WarehouseProduct::query()->where('merchant_id', $shipment->merchant_id)->first();

            if (! $product) {
                continue;
            }

            WarehouseReturn::query()->firstOrCreate(
                ['shipment_id' => $shipment->id, 'warehouse_product_id' => $product->id],
                [
                    'order_id' => $shipment->order_id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 1,
                    'condition' => $shipment->status === ShipmentStatus::RETURNED ? WarehouseReturnCondition::SELLABLE : WarehouseReturnCondition::DAMAGED,
                    'notes' => 'Demo returned shipment handling.',
                    'created_by' => $this->users['warehouse@example.com']->id,
                ],
            );
        }

        foreach ($merchants->take(6) as $index => $merchant) {
            WarehouseCharge::query()->firstOrCreate(
                ['merchant_id' => $merchant->id, 'description' => 'Demo monthly storage charge'],
                [
                    'warehouse_id' => $warehouse->id,
                    'type' => WarehouseChargeType::STORAGE,
                    'quantity' => 1,
                    'unit_price' => 150 + ($index * 25),
                    'amount' => 150 + ($index * 25),
                    'status' => $index % 3 === 0 ? WarehouseChargeStatus::INVOICED : WarehouseChargeStatus::PENDING,
                    'charge_date' => now()->subDays($index)->toDateString(),
                    'notes' => 'Demo warehouse storage charge.',
                    'created_by' => $this->users['warehouse@example.com']->id,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Merchant>  $merchants
     * @param  Collection<int, Driver>  $drivers
     */
    private function seedFinance($merchants, $drivers): void
    {
        if (Schema::hasTable('merchant_payouts')) {
            foreach ($merchants->take(8) as $index => $merchant) {
                $status = $index % 3 === 0 ? PayoutStatus::PENDING : PayoutStatus::COMPLETED;
                MerchantPayout::query()->firstOrCreate(
                    ['reference_number' => 'DEMO-PAYOUT-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'merchant_id' => $merchant->id,
                        'amount' => 1200 + ($index * 350),
                        'status' => $status,
                        'payment_method' => [PaymentMethod::BANK_TRANSFER, PaymentMethod::INSTAPAY, PaymentMethod::WALLET][$index % 3],
                        'notes' => 'Demo merchant payout.',
                        'created_by' => $this->users['finance@example.com']->id,
                        'paid_at' => $status === PayoutStatus::COMPLETED ? now()->subDays($index + 1) : null,
                    ],
                );
            }
        }

        if (Schema::hasTable('driver_cash_closures')) {
            foreach ($drivers->where('status', DriverStatus::ACTIVE)->values() as $index => $driver) {
                $expected = Shipment::query()
                    ->where('assigned_driver_id', $driver->id)
                    ->whereIn('status', [ShipmentStatus::DELIVERED, ShipmentStatus::RETURNED, ShipmentStatus::FAILED])
                    ->sum('cod_amount');
                $expected = max((float) $expected, 800 + ($index * 300));
                $received = $expected - ($index % 3 === 0 ? 50 : 0);

                DriverCashClosure::query()->firstOrCreate(
                    ['driver_id' => $driver->id, 'notes' => 'Demo driver cash closure.'],
                    [
                        'expected_amount' => $expected,
                        'received_amount' => $received,
                        'difference_amount' => $received - $expected,
                        'status' => $index % 2 === 0 ? DriverCashClosureStatus::VERIFIED : DriverCashClosureStatus::PENDING,
                        'created_by' => $this->users['finance@example.com']->id,
                        'verified_by' => $index % 2 === 0 ? $this->users['finance@example.com']->id : null,
                        'verified_at' => $index % 2 === 0 ? now()->subDays($index) : null,
                    ],
                );
            }
        }

        if (Schema::hasTable('expenses')) {
            $expenses = [
                [ExpenseCategory::FUEL, 1850, PaymentMethod::CASH, 'Fuel cards and station receipts'],
                [ExpenseCategory::SALARIES, 12500, PaymentMethod::BANK_TRANSFER, 'Operations team salary sample'],
                [ExpenseCategory::PACKAGING, 3200, PaymentMethod::INSTAPAY, 'Cartons, flyers, and tape'],
                [ExpenseCategory::RENT, 9000, PaymentMethod::BANK_TRANSFER, 'Warehouse monthly rent'],
                [ExpenseCategory::MAINTENANCE, 1450, PaymentMethod::CASH, 'Vehicle service'],
                [ExpenseCategory::OTHER, 750, PaymentMethod::WALLET, 'Office utilities'],
            ];

            foreach ($expenses as $index => [$category, $amount, $method, $notes]) {
                Expense::query()->firstOrCreate(
                    ['reference_number' => 'DEMO-EXP-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'category' => $category,
                        'amount' => $amount,
                        'expense_date' => now()->subDays($index * 3)->toDateString(),
                        'payment_method' => $method,
                        'notes' => $notes,
                        'created_by' => $this->users['finance@example.com']->id,
                    ],
                );
            }
        }

        if (Schema::hasTable('merchant_invoices')) {
            foreach ($merchants->take(6) as $index => $merchant) {
                $orders = Order::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('status', OrderStatus::CONFIRMED)
                    ->get();
                $totalCod = (float) $orders->sum('cod_amount');
                $shippingFees = (float) $orders->sum('shipping_fee');
                $warehouseCharges = Schema::hasTable('warehouse_charges')
                    ? (float) WarehouseCharge::query()->where('merchant_id', $merchant->id)->sum('amount')
                    : 0.0;
                $status = [MerchantInvoiceStatus::DRAFT, MerchantInvoiceStatus::ISSUED, MerchantInvoiceStatus::PAID][$index % 3];

                MerchantInvoice::query()->firstOrCreate(
                    ['invoice_number' => 'INV-'.now()->format('Ym').'-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                    [
                        'merchant_id' => $merchant->id,
                        'period_start' => now()->startOfMonth()->toDateString(),
                        'period_end' => now()->toDateString(),
                        'total_cod' => $totalCod,
                        'total_shipping_fees' => $shippingFees,
                        'total_warehouse_charges' => $warehouseCharges,
                        'total_payable' => max($totalCod - $shippingFees - $warehouseCharges, 0),
                        'status' => $status,
                        'notes' => 'Demo merchant invoice.',
                        'created_by' => $this->users['finance@example.com']->id,
                        'issued_at' => $status === MerchantInvoiceStatus::DRAFT ? null : now()->subDays($index),
                        'generated_at' => $status === MerchantInvoiceStatus::DRAFT ? null : now()->subDays($index),
                        'download_count' => $status === MerchantInvoiceStatus::PAID ? 2 : 0,
                    ],
                );
            }
        }
    }

    private function seedShipmentHistories(Shipment $shipment, string $currentStatus, Carbon $createdAt): void
    {
        $timeline = [
            ShipmentStatus::PENDING_PICKUP,
            ShipmentStatus::PICKED_UP,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::OUT_FOR_DELIVERY,
            ShipmentStatus::DELIVERED,
        ];

        $terminalStatuses = [
            ShipmentStatus::FAILED,
            ShipmentStatus::RETURNED,
            ShipmentStatus::CANCELLED,
        ];

        $statuses = in_array($currentStatus, $terminalStatuses, true)
            ? array_merge(array_slice($timeline, 0, 4), [$currentStatus])
            : array_slice($timeline, 0, array_search($currentStatus, $timeline, true) + 1);

        ShipmentStatusHistory::query()->where('shipment_id', $shipment->id)->delete();

        foreach ($statuses as $index => $status) {
            $history = ShipmentStatusHistory::query()->create([
                'shipment_id' => $shipment->id,
                'status' => $status,
                'notes' => $index === 0 ? 'Shipment created from confirmed order.' : $this->trackingNotes($status),
                'changed_by' => $this->users['staff@example.com']->id,
            ]);
            $this->touch($history, $createdAt->copy()->addHours($index * 6));
        }
    }

    private function shippingFeeFor(int $governorateId, int $areaId): float
    {
        $areaRate = ShippingRate::query()
            ->where('governorate_id', $governorateId)
            ->where('area_id', $areaId)
            ->where('is_active', true)
            ->value('shipping_fee');

        if ($areaRate !== null) {
            return (float) $areaRate;
        }

        return (float) ShippingRate::query()
            ->where('governorate_id', $governorateId)
            ->whereNull('area_id')
            ->where('is_active', true)
            ->value('shipping_fee');
    }

    private function customerName(int $index): string
    {
        $names = [
            'Ahmed Adel',
            'Mona Hossam',
            'Karim Tarek',
            'Sara Mahmoud',
            'Omar Ali',
            'Laila Samir',
            'Nour Mostafa',
            'Youssef Khaled',
            'Hana Nabil',
            'Ibrahim Farid',
        ];

        return $names[$index % count($names)];
    }

    private function trackingNotes(string $status): string
    {
        return match ($status) {
            ShipmentStatus::PENDING_PICKUP => 'Waiting for pickup scheduling.',
            ShipmentStatus::PICKED_UP => 'Collected from merchant.',
            ShipmentStatus::IN_TRANSIT => 'Moving through sorting hub.',
            ShipmentStatus::OUT_FOR_DELIVERY => 'Driver is attempting delivery.',
            ShipmentStatus::DELIVERED => 'Delivered to customer.',
            ShipmentStatus::FAILED => 'Delivery attempt failed.',
            ShipmentStatus::RETURNED => 'Returned to warehouse.',
            ShipmentStatus::CANCELLED => 'Shipment cancelled before completion.',
            default => 'Demo shipment update.',
        };
    }

    private function touch($model, Carbon $date): void
    {
        $model->forceFill([
            'created_at' => $date->toDateTimeString(),
            'updated_at' => $date->toDateTimeString(),
        ])->save();
    }
}

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
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
use App\Support\DemoDataRegistry;
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
