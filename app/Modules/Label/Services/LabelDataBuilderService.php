<?php

namespace App\Modules\Label\Services;

use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Label\Enums\LabelType;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Waybill\Models\Waybill;
use Illuminate\Support\Str;

class LabelDataBuilderService
{
    /**
     * @return array<string, mixed>
     */
    public function forWarehouseProduct(WarehouseProduct $warehouseProduct): array
    {
        $warehouseProduct->loadMissing([
            'merchant',
            'inventoryStocks.warehouse',
            'inventoryStocks.warehouseBin',
        ]);

        /** @var InventoryStock|null $primaryStock */
        $primaryStock = $warehouseProduct->inventoryStocks
            ->sortByDesc(fn (InventoryStock $stock) => $stock->quantity_available + $stock->quantity_reserved)
            ->first();

        return [
            'label_type' => LabelType::WAREHOUSE_PRODUCT,
            'title' => $warehouseProduct->name,
            'subtitle' => $warehouseProduct->merchant?->name,
            'reference' => $warehouseProduct->sku ?: 'WP-'.$warehouseProduct->id,
            'barcode' => $warehouseProduct->barcode,
            'lines' => [
                ['label' => __('labels.sku'), 'value' => $warehouseProduct->sku ?? __('common.not_available')],
                ['label' => __('labels.merchant'), 'value' => $warehouseProduct->merchant?->name ?? __('common.not_available')],
                ['label' => __('labels.warehouse'), 'value' => $primaryStock?->warehouse?->name ?? __('common.not_available')],
                ['label' => __('labels.bin'), 'value' => $primaryStock?->warehouseBin?->code ?? __('common.not_available')],
                ['label' => __('labels.product_ref'), 'value' => '#'.$warehouseProduct->id],
            ],
            'data' => [
                'warehouse_product_id' => $warehouseProduct->id,
                'name' => $warehouseProduct->name,
                'sku' => $warehouseProduct->sku,
                'barcode' => $warehouseProduct->barcode,
                'merchant' => [
                    'id' => $warehouseProduct->merchant_id,
                    'name' => $warehouseProduct->merchant?->name,
                ],
                'warehouse' => [
                    'id' => $primaryStock?->warehouse_id,
                    'name' => $primaryStock?->warehouse?->name,
                ],
                'warehouse_bin' => [
                    'id' => $primaryStock?->warehouse_bin_id,
                    'code' => $primaryStock?->warehouseBin?->code,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forShipment(Shipment $shipment): array
    {
        $shipment->loadMissing([
            'merchant',
            'order',
            'deliveryGovernorate',
            'deliveryArea',
            'waybill',
        ]);

        $notes = $shipment->tracking_notes
            ?: $shipment->order?->package_notes
            ?: $shipment->order?->notes;

        return [
            'label_type' => LabelType::SHIPMENT,
            'title' => __('labels.shipment').' '.$shipment->shipment_number,
            'subtitle' => $shipment->merchant?->name,
            'reference' => $shipment->shipment_number,
            'barcode' => $shipment->waybill?->barcode ?? $shipment->barcode,
            'lines' => [
                ['label' => 'Waybill', 'value' => $shipment->waybill?->waybill_number ?? $shipment->shipment_number],
                ['label' => __('labels.order'), 'value' => $shipment->order?->order_number ?? __('common.not_available')],
                ['label' => __('labels.customer'), 'value' => $shipment->customer_name],
                ['label' => __('labels.phone'), 'value' => $shipment->customer_phone],
                ['label' => __('labels.location'), 'value' => trim(($shipment->deliveryGovernorate?->name ?? '').' / '.($shipment->deliveryArea?->name ?? ''), ' /')],
                ['label' => __('labels.address'), 'value' => $shipment->delivery_address],
                ['label' => __('labels.cod'), 'value' => (string) $shipment->cod_amount],
                ['label' => __('labels.payment'), 'value' => $shipment->order?->payment_type ?? __('common.not_available')],
                ['label' => __('labels.order_type'), 'value' => $shipment->order?->order_type ?? __('common.not_available')],
                ['label' => __('labels.fulfillment'), 'value' => $shipment->order?->fulfillment_type ?? __('common.not_available')],
                ['label' => __('labels.fragile'), 'value' => $shipment->order?->is_fragile ? __('common.yes') : __('common.no')],
                ['label' => __('labels.inspection'), 'value' => $shipment->order?->allow_inspection ? __('common.yes') : __('common.no')],
                ['label' => __('labels.notes'), 'value' => $notes !== null ? Str::limit($notes, 80) : __('common.not_available')],
            ],
            'data' => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'waybill_number' => $shipment->waybill?->waybill_number,
                'barcode' => $shipment->waybill?->barcode ?? $shipment->barcode,
                'order_number' => $shipment->order?->order_number,
                'merchant_name' => $shipment->merchant?->name,
                'customer_name' => $shipment->customer_name,
                'customer_phone' => $shipment->customer_phone,
                'delivery_governorate' => $shipment->deliveryGovernorate?->name,
                'delivery_area' => $shipment->deliveryArea?->name,
                'delivery_address' => $shipment->delivery_address,
                'cod_amount' => $shipment->cod_amount,
                'payment_type' => $shipment->order?->payment_type,
                'order_type' => $shipment->order?->order_type,
                'fulfillment_type' => $shipment->order?->fulfillment_type,
                'is_fragile' => (bool) $shipment->order?->is_fragile,
                'allow_inspection' => (bool) $shipment->order?->allow_inspection,
                'notes' => $notes,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing([
            'merchant',
            'deliveryGovernorate',
            'deliveryArea',
            'items',
            'waybill',
        ]);

        return [
            'label_type' => LabelType::ORDER,
            'title' => __('labels.order').' '.$order->order_number,
            'subtitle' => $order->merchant?->name,
            'reference' => $order->order_number,
            'barcode' => $order->barcode,
            'lines' => [
                ['label' => 'Waybill', 'value' => $order->waybill?->waybill_number ?? __('common.not_available')],
                ['label' => __('labels.merchant'), 'value' => $order->merchant?->name ?? __('common.not_available')],
                ['label' => __('labels.customer'), 'value' => $order->customer_name],
                ['label' => __('labels.phone'), 'value' => $order->customer_phone],
                ['label' => __('labels.items'), 'value' => (string) $order->items->sum('quantity')],
                ['label' => __('labels.fulfillment'), 'value' => $order->fulfillment_type],
                ['label' => __('labels.order_type'), 'value' => $order->order_type],
                ['label' => __('labels.cod'), 'value' => (string) $order->cod_amount],
                ['label' => __('labels.location'), 'value' => trim(($order->deliveryGovernorate?->name ?? '').' / '.($order->deliveryArea?->name ?? ''), ' /')],
            ],
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'waybill_number' => $order->waybill?->waybill_number,
                'barcode' => $order->barcode,
                'merchant_name' => $order->merchant?->name,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'items_count' => $order->items->sum('quantity'),
                'fulfillment_type' => $order->fulfillment_type,
                'order_type' => $order->order_type,
                'cod_amount' => $order->cod_amount,
                'delivery_governorate' => $order->deliveryGovernorate?->name,
                'delivery_area' => $order->deliveryArea?->name,
                'delivery_address' => $order->delivery_address,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forWaybill(Waybill $waybill): array
    {
        $waybill->loadMissing(['order', 'shipment']);

        return [
            'label_type' => LabelType::WAYBILL,
            'title' => 'Waybill '.$waybill->waybill_number,
            'subtitle' => $waybill->source === 'preprinted' ? 'Pre-Printed Waybill' : null,
            'reference' => $waybill->waybill_number,
            'barcode' => $waybill->barcode ?? $waybill->waybill_number,
            'lines' => array_values(array_filter([
                ['label' => 'Status', 'value' => ucfirst($waybill->status)],
                $waybill->order === null ? null : ['label' => __('labels.order'), 'value' => $waybill->order->order_number],
                $waybill->shipment === null ? null : ['label' => __('labels.shipment'), 'value' => $waybill->shipment->shipment_number],
            ])),
            'data' => [
                'waybill_id' => $waybill->id,
                'waybill_number' => $waybill->waybill_number,
                'barcode' => $waybill->barcode,
                'status' => $waybill->status,
                'source' => $waybill->source,
                'batch_id' => $waybill->batch_id,
                'order_id' => $waybill->order_id,
                'shipment_id' => $waybill->shipment_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSimpleWaybill(Waybill $waybill): array
    {
        return [
            'waybill_number' => $waybill->waybill_number,
            'barcode' => $waybill->waybill_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forWarehouseBin(WarehouseBin $warehouseBin): array
    {
        $warehouseBin->loadMissing(['warehouse', 'zone', 'shelf']);

        return [
            'label_type' => LabelType::WAREHOUSE_BIN,
            'title' => $warehouseBin->code ?: $warehouseBin->name,
            'subtitle' => $warehouseBin->warehouse?->name,
            'reference' => $warehouseBin->name,
            'barcode' => $warehouseBin->barcode,
            'lines' => [
                ['label' => __('labels.warehouse'), 'value' => $warehouseBin->warehouse?->name ?? __('common.not_available')],
                ['label' => __('labels.zone'), 'value' => $warehouseBin->zone?->code ?? $warehouseBin->zone?->name ?? __('common.not_available')],
                ['label' => __('labels.shelf'), 'value' => $warehouseBin->shelf?->code ?? $warehouseBin->shelf?->name ?? __('common.not_available')],
                ['label' => __('labels.bin'), 'value' => $warehouseBin->name],
                ['label' => __('labels.bin_code'), 'value' => $warehouseBin->code],
            ],
            'data' => [
                'warehouse_bin_id' => $warehouseBin->id,
                'warehouse_name' => $warehouseBin->warehouse?->name,
                'zone_name' => $warehouseBin->zone?->name,
                'zone_code' => $warehouseBin->zone?->code,
                'shelf_name' => $warehouseBin->shelf?->name,
                'shelf_code' => $warehouseBin->shelf?->code,
                'bin_name' => $warehouseBin->name,
                'bin_code' => $warehouseBin->code,
                'barcode' => $warehouseBin->barcode,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDriverManifest(DriverManifest $manifest): array
    {
        $manifest->loadMissing([
            'driver',
            'items.shipment.order',
            'items.shipment.merchant',
        ]);

        return [
            'label_type' => LabelType::DRIVER_MANIFEST,
            'title' => __('labels.driver_manifest').' '.$manifest->manifest_number,
            'subtitle' => $manifest->driver?->name,
            'reference' => $manifest->manifest_number,
            'barcode' => $manifest->manifest_number,
            'lines' => [
                ['label' => __('labels.date'), 'value' => $manifest->manifest_date?->toDateString() ?? __('common.not_available')],
                ['label' => __('labels.driver'), 'value' => $manifest->driver?->name ?? __('common.not_available')],
                ['label' => __('labels.phone'), 'value' => $manifest->driver?->phone ?? __('common.not_available')],
                ['label' => __('labels.total_shipments'), 'value' => (string) $manifest->total_shipments],
                ['label' => __('labels.expected_cod'), 'value' => (string) $manifest->expected_cod_amount],
                ['label' => __('labels.status'), 'value' => $manifest->status],
            ],
            'items' => $manifest->items->map(function ($item): array {
                return [
                    'shipment_number' => $item->shipment_number,
                    'order_number' => $item->shipment?->order?->order_number,
                    'merchant_name' => $item->shipment?->merchant?->name,
                    'customer_name' => $item->customer_name,
                    'customer_phone' => $item->customer_phone,
                    'delivery_address' => $item->delivery_address,
                    'cod_amount' => $item->cod_amount,
                    'shipping_fee' => $item->shipping_fee,
                    'notes' => $item->notes,
                ];
            })->values()->all(),
            'data' => [
                'manifest_number' => $manifest->manifest_number,
                'manifest_date' => $manifest->manifest_date?->toDateString(),
                'driver_name' => $manifest->driver?->name,
                'driver_phone' => $manifest->driver?->phone,
                'status' => $manifest->status,
                'total_shipments' => $manifest->total_shipments,
                'expected_cod_amount' => $manifest->expected_cod_amount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(string $labelType, array $input = []): array
    {
        $defaultLines = match ($labelType) {
            LabelType::WAREHOUSE_PRODUCT => [
                ['label' => __('labels.sku'), 'value' => 'SKU-001'],
                ['label' => __('labels.merchant'), 'value' => 'Demo Merchant'],
                ['label' => __('labels.warehouse'), 'value' => 'Main Warehouse'],
                ['label' => __('labels.bin'), 'value' => 'A-01'],
            ],
            LabelType::SHIPMENT => [
                ['label' => __('labels.order'), 'value' => 'ORD-000001'],
                ['label' => __('labels.customer'), 'value' => 'John Doe'],
                ['label' => __('labels.phone'), 'value' => '01000000000'],
                ['label' => __('labels.address'), 'value' => 'Nasr City, Cairo'],
                ['label' => __('labels.cod'), 'value' => '250.00'],
            ],
            LabelType::WAYBILL => [
                ['label' => 'Status', 'value' => 'Available'],
            ],
            LabelType::ORDER => [
                ['label' => __('labels.merchant'), 'value' => 'Demo Merchant'],
                ['label' => __('labels.customer'), 'value' => 'Jane Doe'],
                ['label' => __('labels.phone'), 'value' => '01000000001'],
                ['label' => __('labels.items'), 'value' => '3'],
            ],
            LabelType::WAREHOUSE_BIN => [
                ['label' => __('labels.warehouse'), 'value' => 'Main Warehouse'],
                ['label' => __('labels.zone'), 'value' => 'A'],
                ['label' => __('labels.shelf'), 'value' => '01'],
                ['label' => __('labels.bin'), 'value' => 'A-01-02'],
            ],
            default => [
                ['label' => __('labels.reference'), 'value' => 'DM-20260529-000001'],
                ['label' => __('labels.driver'), 'value' => 'Driver 1'],
                ['label' => __('labels.total_shipments'), 'value' => '12'],
            ],
        };

        return [
            'label_type' => $labelType,
            'title' => $input['title'] ?? Str::headline(str_replace('_', ' ', $labelType)).' '.__('labels.preview'),
            'subtitle' => $input['subtitle'] ?? 'Demo Data',
            'reference' => $input['reference'] ?? 'REF-0001',
            'barcode' => $input['barcode'] ?? ($labelType === LabelType::WAYBILL ? 'WB-000001' : 'LBL-DEMO-000001'),
            'lines' => $input['lines'] ?? $defaultLines,
            'items' => $input['items'] ?? [],
            'data' => [
                'preview' => true,
                'label_type' => $labelType,
            ],
        ];
    }
}
