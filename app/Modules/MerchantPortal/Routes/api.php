<?php

use App\Modules\MerchantPortal\Controllers\MerchantInboundStockRequestAdminController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalAttachmentController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalDashboardController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalExportController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalFinanceController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalInboundStockRequestController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalInventoryController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalIntegrationTokenController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalInvoiceController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalLocationController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalMeController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalNotificationController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalOrderController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalPayoutController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalProfileController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalShipmentController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalSettingsController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalStockMovementController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalUserManagementController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalWalletController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalWarehouseChargeController;
use App\Modules\MerchantPortal\Controllers\MerchantPortalWarehouseProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'merchant.portal'])->prefix('merchant-portal')->group(function () {
    Route::get('/me', [MerchantPortalMeController::class, 'show']);
    Route::get('/profile', [MerchantPortalProfileController::class, 'show']);
    Route::put('/profile', [MerchantPortalProfileController::class, 'update']);
    Route::get('/settings', [MerchantPortalSettingsController::class, 'show']);
    Route::put('/settings', [MerchantPortalSettingsController::class, 'update']);
    Route::get('/dashboard/summary', [MerchantPortalDashboardController::class, 'summary']);

    Route::prefix('locations')->group(function () {
        Route::get('/governorates', [MerchantPortalLocationController::class, 'governorates']);
        Route::get('/areas', [MerchantPortalLocationController::class, 'areas']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('/', [MerchantPortalOrderController::class, 'index']);
        Route::post('/', [MerchantPortalOrderController::class, 'store']);
        Route::get('/{order}', [MerchantPortalOrderController::class, 'show']);
        Route::put('/{order}', [MerchantPortalOrderController::class, 'update']);
        Route::post('/{order}/confirm', [MerchantPortalOrderController::class, 'confirm']);
        Route::post('/{order}/cancel', [MerchantPortalOrderController::class, 'cancel']);
        Route::get('/{order}/label', [MerchantPortalOrderController::class, 'label']);
    });

    Route::prefix('shipments')->group(function () {
        Route::get('/', [MerchantPortalShipmentController::class, 'index']);
        Route::get('/{shipment}', [MerchantPortalShipmentController::class, 'show']);
        Route::get('/{shipment}/tracking', [MerchantPortalShipmentController::class, 'tracking']);
        Route::get('/{shipment}/label', [MerchantPortalShipmentController::class, 'label']);
        Route::get('/{shipment}/waybill', [MerchantPortalShipmentController::class, 'waybill']);
    });

    Route::prefix('warehouse')->group(function () {
        Route::prefix('products')->group(function () {
            Route::post('/labels/bulk', [MerchantPortalWarehouseProductController::class, 'bulkLabels']);
            Route::get('/', [MerchantPortalWarehouseProductController::class, 'index']);
            Route::get('/{warehouseProduct}', [MerchantPortalWarehouseProductController::class, 'show']);
            Route::get('/{warehouseProduct}/label', [MerchantPortalWarehouseProductController::class, 'label']);
        });

        Route::prefix('inventory')->group(function () {
            Route::get('/', [MerchantPortalInventoryController::class, 'index']);
            Route::get('/low-stock', [MerchantPortalInventoryController::class, 'lowStock']);
            Route::get('/{inventoryStock}', [MerchantPortalInventoryController::class, 'show']);
        });

        Route::get('/movements', [MerchantPortalStockMovementController::class, 'index']);
        Route::get('/charges', [MerchantPortalWarehouseChargeController::class, 'index']);

        Route::prefix('inbound-requests')->group(function () {
            Route::get('/', [MerchantPortalInboundStockRequestController::class, 'index']);
            Route::post('/', [MerchantPortalInboundStockRequestController::class, 'store']);
            Route::get('/{inboundRequest}', [MerchantPortalInboundStockRequestController::class, 'show']);
            Route::post('/{inboundRequest}/cancel', [MerchantPortalInboundStockRequestController::class, 'cancel']);
        });
    });

    Route::prefix('finance')->group(function () {
        Route::get('/summary', [MerchantPortalFinanceController::class, 'summary']);

        Route::prefix('wallet')->group(function () {
            Route::get('/summary', [MerchantPortalWalletController::class, 'summary']);
            Route::get('/transactions', [MerchantPortalWalletController::class, 'transactions']);
            Route::get('/transactions/{transaction}', [MerchantPortalWalletController::class, 'show']);
        });

        Route::prefix('invoices')->group(function () {
            Route::get('/', [MerchantPortalInvoiceController::class, 'index']);
            Route::get('/{invoice}', [MerchantPortalInvoiceController::class, 'show']);
            Route::get('/{invoice}/preview', [MerchantPortalInvoiceController::class, 'preview']);
            Route::get('/{invoice}/download', [MerchantPortalInvoiceController::class, 'download']);
        });

        Route::prefix('payouts')->group(function () {
            Route::get('/', [MerchantPortalPayoutController::class, 'index']);
            Route::get('/{payout}', [MerchantPortalPayoutController::class, 'show']);
            Route::post('/request', [MerchantPortalPayoutController::class, 'requestPayout']);
        });
    });

    Route::prefix('integration-tokens')->group(function () {
        Route::get('/', [MerchantPortalIntegrationTokenController::class, 'index']);
        Route::post('/', [MerchantPortalIntegrationTokenController::class, 'store']);
        Route::get('/{token}', [MerchantPortalIntegrationTokenController::class, 'show']);
        Route::post('/{token}/revoke', [MerchantPortalIntegrationTokenController::class, 'revoke']);
        Route::delete('/{token}', [MerchantPortalIntegrationTokenController::class, 'destroy']);
    });

    Route::prefix('integrations')->group(function () {
        Route::get('/docs', [MerchantPortalIntegrationTokenController::class, 'docs']);
        Route::post('/test', [MerchantPortalIntegrationTokenController::class, 'test']);
    });

    Route::prefix('exports')->group(function () {
        Route::get('/types', [MerchantPortalExportController::class, 'types']);
        Route::post('/', [MerchantPortalExportController::class, 'store']);
        Route::get('/', [MerchantPortalExportController::class, 'index']);
        Route::get('/{export}', [MerchantPortalExportController::class, 'show']);
        Route::get('/{export}/download', [MerchantPortalExportController::class, 'download']);
    });

    Route::prefix('attachments')->group(function () {
        Route::get('/', [MerchantPortalAttachmentController::class, 'index']);
        Route::post('/', [MerchantPortalAttachmentController::class, 'store']);
        Route::get('/{attachment}', [MerchantPortalAttachmentController::class, 'show']);
        Route::get('/{attachment}/download', [MerchantPortalAttachmentController::class, 'download']);
        Route::delete('/{attachment}', [MerchantPortalAttachmentController::class, 'destroy']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [MerchantPortalNotificationController::class, 'index']);
        Route::get('/unread-count', [MerchantPortalNotificationController::class, 'unreadCount']);
        Route::post('/{notification}/mark-read', [MerchantPortalNotificationController::class, 'markRead']);
        Route::post('/mark-all-read', [MerchantPortalNotificationController::class, 'markAllRead']);
    });
});

Route::middleware(['auth:sanctum', 'permission:merchant_portal_users.manage'])
    ->prefix('merchants/{merchant}/portal-users')
    ->group(function () {
        Route::get('/', [MerchantPortalUserManagementController::class, 'index']);
        Route::post('/', [MerchantPortalUserManagementController::class, 'store']);
        Route::get('/{merchantUser}', [MerchantPortalUserManagementController::class, 'show']);
        Route::put('/{merchantUser}', [MerchantPortalUserManagementController::class, 'update']);
        Route::delete('/{merchantUser}', [MerchantPortalUserManagementController::class, 'destroy']);
        Route::post('/{merchantUser}/suspend', [MerchantPortalUserManagementController::class, 'suspend']);
        Route::post('/{merchantUser}/activate', [MerchantPortalUserManagementController::class, 'activate']);
    });

Route::middleware(['auth:sanctum', 'permission:merchant_inbound_requests.view'])
    ->prefix('merchant-inbound-stock-requests')
    ->group(function () {
        Route::get('/', [MerchantInboundStockRequestAdminController::class, 'index']);
        Route::get('/{inboundRequest}', [MerchantInboundStockRequestAdminController::class, 'show']);
    });

Route::middleware(['auth:sanctum', 'permission:merchant_inbound_requests.manage'])
    ->prefix('merchant-inbound-stock-requests')
    ->group(function () {
        Route::post('/{inboundRequest}/approve', [MerchantInboundStockRequestAdminController::class, 'approve']);
        Route::post('/{inboundRequest}/reject', [MerchantInboundStockRequestAdminController::class, 'reject']);
        Route::post('/{inboundRequest}/link-warehouse-receipt', [MerchantInboundStockRequestAdminController::class, 'linkWarehouseReceipt']);
    });
