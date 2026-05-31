<?php

use App\Models\Merchant;
use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use App\Modules\Finance\Models\TreasuryTransaction;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\WarehouseReturn;

return [
    'attachable_types' => [
        'merchants' => Merchant::class,
        'orders' => Order::class,
        'shipments' => Shipment::class,
        'expenses' => Expense::class,
        'merchant_invoices' => MerchantInvoice::class,
        'merchant_payouts' => MerchantPayout::class,
        'merchant_integration_tokens' => MerchantIntegrationToken::class,
        'merchant_inbound_stock_requests' => MerchantInboundStockRequest::class,
        'warehouse_returns' => WarehouseReturn::class,
        'treasury_transactions' => TreasuryTransaction::class,
        'driver_manifests' => DriverManifest::class,
    ],
];
