<?php

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Attachment\Models\Attachment;
use App\Modules\CompanyCatalog\Models\CompanyCatalogItem;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Export\Models\ExportLog;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Models\TreasuryTransaction;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\Order\Models\Order;
use App\Modules\RolePermission\Models\Permission;
use App\Modules\RolePermission\Models\Role;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\PackingTask;
use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Models\WarehouseReturn;

return [
    'excluded_fields' => [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'tokens',
        'api_token',
        'api_key',
        'secret',
        'secrets',
    ],

    'excluded_auto_fields' => [
        'updated_at',
    ],

    'aliases' => [
        'user' => User::class,
        'merchant' => Merchant::class,
        'order' => Order::class,
        'shipment' => Shipment::class,
        'driver' => Driver::class,
        'driver_manifest' => DriverManifest::class,
        'warehouse_product' => WarehouseProduct::class,
        'warehouse_return' => WarehouseReturn::class,
        'stock_movement' => StockMovement::class,
        'picking_list' => PickingList::class,
        'packing_task' => PackingTask::class,
        'wallet_transaction' => MerchantWalletTransaction::class,
        'treasury_transaction' => TreasuryTransaction::class,
        'merchant_payout' => MerchantPayout::class,
        'expense' => Expense::class,
        'role' => Role::class,
        'permission' => Permission::class,
        'system_setting' => SystemSetting::class,
        'attachment' => Attachment::class,
        'export_log' => ExportLog::class,
        'merchant_integration_token' => MerchantIntegrationToken::class,
        'company_catalog_item' => CompanyCatalogItem::class,
    ],
];
