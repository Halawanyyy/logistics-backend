<?php

namespace App\Support;

final class DemoDataRegistry
{
    public const MARKER = '[DEMO_DATA]';
    public const PASSWORD = 'DemoPass123!';

    public const INTERNAL_USER_EMAIL = 'demo.admin@example.com';
    public const MERCHANT_PORTAL_USER_EMAIL = 'demo.merchant@example.com';
    public const MERCHANT_EMAIL = 'demo.ops@example.com';

    public const GOVERNORATE_NAME = 'DEMO Governorate';
    public const AREA_NAME = 'DEMO Area';

    public const WAREHOUSE_CODE = 'DEMO-WH-01';
    public const WAREHOUSE_ZONE_CODE = 'DEMO-ZONE-A';
    public const WAREHOUSE_SHELF_CODE = 'DEMO-SHELF-01';
    public const WAREHOUSE_BIN_CODE = 'DEMO-BIN-01';
    public const WAREHOUSE_BIN_BARCODE = 'DEMO-BIN-BC-01';

    public const DRIVER_PHONE = '01110000001';
    public const PRODUCT_SKU = 'DEMO-SKU-001';
    public const PRODUCT_BARCODE = 'DEMO-PRODUCT-BC-001';

    public const ORDER_NUMBER = 'DEMO-ORDER-0001';
    public const SHIPMENT_NUMBER = 'DEMO-SHIP-0001';
    public const WALLET_TRANSACTION_NUMBER = 'DEMO-WALLET-0001';
    public const INVOICE_NUMBER = 'DEMO-INV-0001';
    public const PAYOUT_REFERENCE = 'DEMO-PAYOUT-0001';

    public static function note(string $context): string
    {
        return sprintf('%s %s', self::MARKER, $context);
    }

    /**
     * @return list<string>
     */
    public static function userEmails(): array
    {
        return [
            self::INTERNAL_USER_EMAIL,
            self::MERCHANT_PORTAL_USER_EMAIL,
        ];
    }

    /**
     * @return list<string>
     */
    public static function orderNumbers(): array
    {
        return [
            self::ORDER_NUMBER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function shipmentNumbers(): array
    {
        return [
            self::SHIPMENT_NUMBER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function warehouseCodes(): array
    {
        return [
            self::WAREHOUSE_CODE,
        ];
    }
}
