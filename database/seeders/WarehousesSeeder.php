<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\LocationPricing\Models\Area;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\PickPackFeeRule;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StorageFeeRule;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use Illuminate\Database\Seeder;

class WarehousesSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseUserId = User::query()->where('email', 'warehouse@example.com')->value('id');
        $merchants = Merchant::query()->where('status', 'active')->with('pricingPlan')->get();

        $catalogItems = [
            [
                'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
                'category' => 'flyer',
                'name' => 'Flyer Medium',
                'sku' => 'PKG-FLYER-M',
                'description' => 'Medium flyer bag for compact orders.',
                'unit_price' => '3.50',
                'currency' => 'EGP',
                'size_label' => 'Medium',
                'length' => 30,
                'width' => 20,
                'unit' => 'cm',
                'sort_order' => 10,
            ],
            [
                'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
                'category' => 'carton',
                'name' => 'Carton Small',
                'sku' => 'PKG-CARTON-S',
                'description' => 'Small corrugated carton.',
                'unit_price' => '12.00',
                'currency' => 'EGP',
                'size_label' => 'Small',
                'length' => 25,
                'width' => 20,
                'height' => 12,
                'unit' => 'cm',
                'sort_order' => 20,
            ],
            [
                'type' => CompanyCatalogItem::TYPE_PACKAGING_PRODUCT,
                'category' => 'sticker',
                'name' => 'Fragile Sticker',
                'sku' => 'PKG-STICKER-FRAGILE',
                'description' => 'Handle-with-care sticker.',
                'unit_price' => '1.50',
                'currency' => 'EGP',
                'size_label' => 'Single',
                'sort_order' => 30,
            ],
            [
                'type' => CompanyCatalogItem::TYPE_STORAGE_SERVICE,
                'category' => 'storage',
                'name' => 'Storage Service',
                'sku' => 'SRV-STORAGE',
                'description' => 'Merchant-visible storage service reference item.',
                'unit_price' => '0.00',
                'currency' => 'EGP',
                'sort_order' => 40,
            ],
            [
                'type' => CompanyCatalogItem::TYPE_FULFILLMENT_SERVICE,
                'category' => 'pick_pack',
                'name' => 'Pick & Pack Service',
                'sku' => 'SRV-PICK-PACK',
                'description' => 'Merchant-visible pick and pack service reference item.',
                'unit_price' => '0.00',
                'currency' => 'EGP',
                'sort_order' => 50,
            ],
        ];

        foreach ($catalogItems as $item) {
            CompanyCatalogItem::query()->withTrashed()->updateOrCreate(
                ['sku' => $item['sku']],
                array_merge($item, [
                    'status' => CompanyCatalogItem::STATUS_ACTIVE,
                    'is_visible_to_merchants' => true,
                    'created_by' => $warehouseUserId,
                    'updated_by' => $warehouseUserId,
                    'deleted_at' => null,
                ]),
            );
        }

        $warehouseConfigs = [
            [
                'code' => 'WH-CAI-01',
                'name' => 'Cairo Main Warehouse',
                'area' => 'Nasr City',
                'governorate' => 'Cairo',
                'address' => 'Industrial Zone, Nasr City, Cairo',
            ],
            [
                'code' => 'WH-GIZ-01',
                'name' => 'Giza Overflow Warehouse',
                'area' => '6th of October',
                'governorate' => 'Giza',
                'address' => 'Logistics Park, 6th of October, Giza',
            ],
        ];

        foreach ($warehouseConfigs as $config) {
            $area = Area::query()
                ->where('name', $config['area'])
                ->whereHas('governorate', fn ($query) => $query->where('name', $config['governorate']))
                ->first();

            $warehouse = Warehouse::query()->withTrashed()->updateOrCreate(
                ['code' => $config['code']],
                [
                    'name' => $config['name'],
                    'address' => $config['address'],
                    'governorate_id' => $area?->governorate_id,
                    'area_id' => $area?->id,
                    'status' => 'active',
                    'notes' => 'Seeded warehouse for admin ERP testing.',
                    'deleted_at' => null,
                ],
            );

            $zones = [
                ['Receiving', 'RCV'],
                ['Fast Moving', 'FST'],
                ['Returns', 'RTN'],
                ['Packing', 'PCK'],
            ];

            foreach ($zones as [$zoneName, $zoneCode]) {
                $zone = WarehouseZone::query()->withTrashed()->updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'code' => $zoneCode],
                    ['name' => $zoneName, 'status' => 'active', 'notes' => null, 'deleted_at' => null],
                );

                for ($i = 1; $i <= 2; $i++) {
                    $shelf = WarehouseShelf::query()->withTrashed()->updateOrCreate(
                        ['warehouse_id' => $warehouse->id, 'code' => $zoneCode.'-S'.$i],
                        [
                            'warehouse_zone_id' => $zone->id,
                            'name' => $zoneName.' Shelf '.$i,
                            'status' => 'active',
                            'notes' => null,
                            'deleted_at' => null,
                        ],
                    );

                    for ($j = 1; $j <= 2; $j++) {
                        $bin = WarehouseBin::query()->withTrashed()->updateOrCreate(
                            ['warehouse_id' => $warehouse->id, 'code' => $zoneCode.'-S'.$i.'-B'.$j],
                            [
                                'warehouse_zone_id' => $zone->id,
                                'warehouse_shelf_id' => $shelf->id,
                                'name' => $zoneName.' Bin '.$i.'-'.$j,
                                'barcode' => null,
                                'status' => 'active',
                                'notes' => null,
                                'deleted_at' => null,
                            ],
                        );

                        $barcode = $this->uniqueWarehouseBinBarcode(
                            warehouseId: $warehouse->id,
                            zoneId: $zone->id,
                            shelfId: $shelf->id,
                            binCode: $bin->code,
                            ignoreBinId: $bin->id,
                        );

                        if ($bin->barcode !== $barcode) {
                            $bin->forceFill(['barcode' => $barcode])->save();
                        }
                    }
                }
            }
        }

        $productTemplates = [
            ['Wireless Earbuds', 'EAR', 0.30, 12, 10, 6, true, true],
            ['Cotton T-Shirt', 'TSH', 0.25, 24, 18, 3, false, false],
            ['Ceramic Mug Set', 'MUG', 1.20, 28, 20, 16, true, true],
            ['Phone Case', 'CAS', 0.15, 16, 10, 4, false, false],
            ['Desk Lamp', 'LMP', 1.40, 35, 18, 14, true, true],
            ['Kids Backpack', 'BAG', 0.80, 30, 24, 12, false, true],
        ];

        foreach ($merchants->take(6) as $merchant) {
            StorageFeeRule::query()->withTrashed()->updateOrCreate(
                ['merchant_id' => $merchant->id, 'name' => $merchant->company_name.' Storage Rule'],
                [
                    'pricing_plan_id' => $merchant->pricing_plan_id,
                    'calculation_type' => 'per_item_per_month',
                    'free_units' => 20,
                    'unit_price' => 2.50,
                    'status' => 'active',
                    'notes' => 'Seeded default storage rule.',
                    'deleted_at' => null,
                ],
            );

            PickPackFeeRule::query()->withTrashed()->updateOrCreate(
                ['merchant_id' => $merchant->id, 'name' => $merchant->company_name.' Pick Pack Rule'],
                [
                    'pricing_plan_id' => $merchant->pricing_plan_id,
                    'pick_fee_first_item' => 6.00,
                    'pick_fee_additional_item' => 2.50,
                    'packing_fee_base' => 4.00,
                    'fragile_extra_fee' => 3.00,
                    'status' => 'active',
                    'notes' => 'Seeded default pick-pack rule.',
                    'deleted_at' => null,
                ],
            );

            foreach ($productTemplates as $index => [$name, $skuPrefix, $weight, $length, $width, $height, $fragile, $packaging]) {
                $product = WarehouseProduct::query()->withTrashed()->updateOrCreate(
                    ['merchant_id' => $merchant->id, 'sku' => $skuPrefix.'-'.$merchant->id.'-'.($index + 1)],
                    [
                        'name' => $name,
                        'barcode' => 'WH-PROD-'.$merchant->id.'-'.now()->format('Ymd').'-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                        'description' => 'Seeded warehouse product for '.$merchant->company_name,
                        'unit_weight' => $weight,
                        'unit_length' => $length,
                        'unit_width' => $width,
                        'unit_height' => $height,
                        'is_fragile' => $fragile,
                        'requires_packaging' => $packaging,
                        'status' => 'active',
                        'notes' => null,
                        'deleted_at' => null,
                    ],
                );

                foreach (Warehouse::query()->get() as $warehouse) {
                    $bin = WarehouseBin::query()->where('warehouse_id', $warehouse->id)->inRandomOrder()->first();
                    $available = 20 + ($merchant->id % 5) * 5 + $index * 3;
                    $reserved = $index % 3;
                    $damaged = $index === 2 ? 1 : 0;

                    InventoryStock::query()->updateOrCreate(
                        ['warehouse_id' => $warehouse->id, 'warehouse_product_id' => $product->id],
                        [
                            'warehouse_bin_id' => $bin?->id,
                            'quantity_available' => $available,
                            'quantity_reserved' => $reserved,
                            'quantity_damaged' => $damaged,
                        ],
                    );

                    StockMovement::query()->updateOrCreate(
                        [
                            'warehouse_id' => $warehouse->id,
                            'warehouse_product_id' => $product->id,
                            'type' => 'in',
                            'reference_type' => 'seed_initial_stock',
                            'reference_id' => $warehouse->id * 100000 + $product->id,
                        ],
                        [
                            'quantity' => $available + $reserved + $damaged,
                            'before_available' => 0,
                            'after_available' => $available,
                            'before_reserved' => 0,
                            'after_reserved' => $reserved,
                            'before_damaged' => 0,
                            'after_damaged' => $damaged,
                            'notes' => 'Initial seeded warehouse stock.',
                            'created_by' => $warehouseUserId,
                        ],
                    );
                }
            }
        }
    }

    private function uniqueWarehouseBinBarcode(
        int $warehouseId,
        int $zoneId,
        int $shelfId,
        string $binCode,
        ?int $ignoreBinId = null,
    ): string {
        $baseBarcode = sprintf(
            'BIN-BC-%d-%d-%d-%s',
            $warehouseId,
            $zoneId,
            $shelfId,
            strtoupper(str_replace('-', '', $binCode)),
        );

        $barcode = $baseBarcode;
        $suffix = 1;

        while (
            WarehouseBin::query()
                ->when($ignoreBinId !== null, fn ($query) => $query->whereKeyNot($ignoreBinId))
                ->where('barcode', $barcode)
                ->exists()
        ) {
            $barcode = $baseBarcode.'-'.$suffix;
            $suffix++;
        }

        return $barcode;
    }
}
