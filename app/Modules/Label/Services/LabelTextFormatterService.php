<?php

namespace App\Modules\Label\Services;

use App\Modules\Label\Support\LabelType;

class LabelTextFormatterService
{
    /**
     * @param  array<string, mixed>  $label
     * @return list<string>
     */
    public function lines(string $labelType, array $label): array
    {
        return match ($labelType) {
            LabelType::WAREHOUSE_PRODUCT => [
                'Warehouse Product',
                'Product: '.$label['product_name'],
                'SKU: '.($label['sku'] ?? 'N/A'),
                'Barcode: '.$label['barcode'],
                'Merchant: '.($label['merchant_name'] ?? 'N/A'),
                'Warehouse: '.($label['warehouse_name'] ?? 'N/A'),
                'Bin: '.($label['bin_code'] ?? 'N/A'),
                'Ref: '.($label['product_reference'] ?? 'N/A'),
            ],
            LabelType::SHIPMENT => [
                'Shipment Waybill',
                'Shipment: '.$label['shipment_number'],
                'Barcode: '.$label['barcode'],
                'Order: '.($label['order_number'] ?? 'N/A'),
                'Merchant: '.($label['merchant_name'] ?? 'N/A'),
                'Customer: '.($label['customer_name'] ?? 'N/A'),
                'Phone: '.($label['customer_phone'] ?? 'N/A'),
                'Area: '.trim(implode(', ', array_filter([
                    $label['delivery_governorate_name'] ?? null,
                    $label['delivery_area_name'] ?? null,
                ]))),
                'Address: '.($label['delivery_address'] ?? 'N/A'),
                'COD: '.($label['cod_amount'] ?? '0.00'),
                'Payment: '.($label['payment_type'] ?? 'N/A'),
                'Order Type: '.($label['order_type'] ?? 'N/A'),
                'Fulfillment: '.($label['fulfillment_type'] ?? 'N/A'),
                'Fragile: '.(! empty($label['is_fragile']) ? 'Yes' : 'No'),
                'Inspection: '.(! empty($label['allow_inspection']) ? 'Yes' : 'No'),
                'Notes: '.($label['notes'] ?? '-'),
            ],
            LabelType::ORDER => [
                'Order Label',
                'Order: '.$label['order_number'],
                'Barcode: '.$label['barcode'],
                'Merchant: '.($label['merchant_name'] ?? 'N/A'),
                'Customer: '.($label['customer_name'] ?? 'N/A'),
                'Phone: '.($label['customer_phone'] ?? 'N/A'),
                'Items: '.($label['items_count'] ?? 0),
                'Fulfillment: '.($label['fulfillment_type'] ?? 'N/A'),
                'Order Type: '.($label['order_type'] ?? 'N/A'),
                'COD: '.($label['cod_amount'] ?? '0.00'),
                'Delivery: '.($label['delivery_summary'] ?? 'N/A'),
            ],
            LabelType::WAREHOUSE_BIN => [
                'Warehouse Bin',
                'Warehouse: '.($label['warehouse_name'] ?? 'N/A'),
                'Zone: '.trim(implode(' / ', array_filter([
                    $label['zone_name'] ?? null,
                    $label['zone_code'] ?? null,
                ]))),
                'Shelf: '.trim(implode(' / ', array_filter([
                    $label['shelf_name'] ?? null,
                    $label['shelf_code'] ?? null,
                ]))),
                'Bin: '.trim(implode(' / ', array_filter([
                    $label['bin_name'] ?? null,
                    $label['bin_code'] ?? null,
                ]))),
                'Barcode: '.($label['barcode'] ?? 'N/A'),
            ],
            default => $this->manifestLines($label),
        };
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    public function manifestLines(array $manifest): array
    {
        $lines = [
            'Driver Manifest',
            'Manifest: '.($manifest['manifest_number'] ?? 'N/A'),
            'Date: '.($manifest['manifest_date'] ?? 'N/A'),
            'Driver: '.($manifest['driver_name'] ?? 'N/A'),
            'Phone: '.($manifest['driver_phone'] ?? 'N/A'),
            'Status: '.($manifest['status'] ?? 'N/A'),
            'Expected COD: '.($manifest['expected_cod_amount'] ?? '0.00'),
            'Notes: '.($manifest['notes'] ?? '-'),
            'Shipments:',
        ];

        foreach (($manifest['shipments'] ?? []) as $shipment) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | COD %s',
                $shipment['shipment_number'] ?? 'N/A',
                $shipment['order_number'] ?? 'N/A',
                $shipment['customer_name'] ?? 'N/A',
                $shipment['merchant_name'] ?? 'N/A',
                $shipment['cod_amount'] ?? '0.00'
            );
        }

        return $lines;
    }
}
