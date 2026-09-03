<?php

use App\Modules\Finance\Controllers\DriverCashClosureController;
use App\Modules\Finance\Controllers\ExpenseController;
use App\Modules\Finance\Controllers\FinanceReportController;
use App\Modules\Finance\Controllers\FinanceReportsController;
use App\Modules\Finance\Controllers\MerchantFinanceController;
use App\Modules\Finance\Controllers\MerchantInvoiceController;
use App\Modules\Finance\Controllers\MerchantPayoutController;
use App\Modules\Finance\Controllers\MerchantWalletController;
use App\Modules\Finance\Controllers\MerchantWalletTransactionController;
use App\Modules\Finance\Controllers\TreasuryAccountController;
use App\Modules\Finance\Controllers\TreasuryTransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('driver-cash-closures')->group(function () {
        Route::get('/', [DriverCashClosureController::class, 'index'])->middleware('permission:drivers.cash_closure');
        Route::post('/', [DriverCashClosureController::class, 'store'])->middleware('permission:drivers.cash_closure');
        Route::get('/{driverCashClosure}', [DriverCashClosureController::class, 'show'])->middleware('permission:drivers.cash_closure');
        Route::put('/{driverCashClosure}', [DriverCashClosureController::class, 'update'])->middleware('permission:drivers.cash_closure');
        Route::post('/{driverCashClosure}/verify', [DriverCashClosureController::class, 'verify'])->middleware('permission:drivers.cash_closure.verify');
        Route::delete('/{driverCashClosure}', [DriverCashClosureController::class, 'destroy'])->middleware('permission:drivers.cash_closure');
    });

    Route::post('/drivers/{driver}/cash-closures/generate', [DriverCashClosureController::class, 'generate'])->middleware('permission:drivers.cash_closure');
    Route::get('/drivers/{driver}/cash-expected', [DriverCashClosureController::class, 'expected'])->middleware('permission:drivers.cash_closure');

    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:finance.expenses.view');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:finance.expenses.create');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:finance.expenses.view');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:finance.expenses.update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:finance.expenses.update');
        Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('permission:finance.expenses.approve');
        Route::post('/{expense}/reject', [ExpenseController::class, 'reject'])->middleware('permission:finance.expenses.reject');
        Route::post('/{expense}/mark-paid', [ExpenseController::class, 'markPaid'])->middleware('permission:finance.expenses.pay');
    });

    Route::prefix('merchant-payouts')->group(function () {
        Route::get('/', [MerchantPayoutController::class, 'index'])->middleware('permission:finance.payouts.view');
        Route::post('/', [MerchantPayoutController::class, 'store'])->middleware('permission:finance.payouts.create');
        Route::get('/{merchantPayout}', [MerchantPayoutController::class, 'show'])->middleware('permission:finance.payouts.view');
        Route::put('/{merchantPayout}', [MerchantPayoutController::class, 'update'])->middleware('permission:finance.payouts.update');
        Route::delete('/{merchantPayout}', [MerchantPayoutController::class, 'destroy'])->middleware('permission:finance.payouts.update');
        Route::post('/{merchantPayout}/approve', [MerchantPayoutController::class, 'approve'])->middleware('permission:finance.payouts.approve');
        Route::post('/{merchantPayout}/reject', [MerchantPayoutController::class, 'reject'])->middleware('permission:finance.payouts.reject');
        Route::post('/{merchantPayout}/complete', [MerchantPayoutController::class, 'complete'])->middleware('permission:finance.payouts.complete');
    });

    Route::prefix('merchant-invoices')->group(function () {
        Route::get('/', [MerchantInvoiceController::class, 'index'])->middleware('permission:finance.invoices.view');
        Route::post('/', [MerchantInvoiceController::class, 'store'])->middleware('permission:finance.invoices.create');
        Route::get('/{merchantInvoice}', [MerchantInvoiceController::class, 'show'])->middleware('permission:finance.invoices.view');
        Route::get('/{merchantInvoice}/preview', [MerchantInvoiceController::class, 'preview'])->middleware('permission:finance.invoices.view');
        Route::get('/{merchantInvoice}/download', [MerchantInvoiceController::class, 'download'])->middleware('permission:finance.invoices.download');
        Route::put('/{merchantInvoice}', [MerchantInvoiceController::class, 'update'])->middleware('permission:finance.invoices.create');
        Route::delete('/{merchantInvoice}', [MerchantInvoiceController::class, 'destroy'])->middleware('permission:finance.invoices.create');
    });

    Route::get('/finance/company-profit-summary', [FinanceReportController::class, 'companyProfitSummary'])->middleware('permission:finance.reports.view');
    Route::get('/finance/reconciliation-summary', [FinanceReportController::class, 'reconciliationSummary'])->middleware('permission:finance.reconciliation.view');
    Route::prefix('finance/reports')->group(function () {
        Route::get('/overview', [FinanceReportsController::class, 'overview'])->middleware('permission:finance.reports.view');
        Route::get('/merchant/{merchant}', [FinanceReportsController::class, 'merchant'])->middleware('permission:finance.reports.view');
        Route::get('/drivers', [FinanceReportsController::class, 'drivers'])->middleware('permission:finance.reports.view');
        Route::get('/expenses', [FinanceReportsController::class, 'expenses'])->middleware('permission:finance.reports.view');
        Route::get('/payouts', [FinanceReportsController::class, 'payouts'])->middleware('permission:finance.reports.view');
        Route::get('/daily-cash', [FinanceReportsController::class, 'dailyCash'])->middleware('permission:finance.reports.daily_cash');
        Route::get('/merchant-ledger', [FinanceReportsController::class, 'merchantLedger'])->middleware('permission:finance.reports.merchant_ledger');
        Route::get('/driver-discrepancies', [FinanceReportsController::class, 'driverDiscrepancies'])->middleware('permission:finance.reports.driver_discrepancies');
        Route::get('/warehouse-revenue', [FinanceReportsController::class, 'warehouseRevenue'])->middleware('permission:finance.reports.warehouse_revenue');
        Route::get('/profit-loss', [FinanceReportsController::class, 'profitLoss'])->middleware('permission:finance.reports.profit_loss');
    });
    Route::get('/merchants/{merchant}/finance/summary', [MerchantFinanceController::class, 'summary'])->middleware('permission:finance.view');

    Route::prefix('merchants/{merchant}/wallet')->group(function () {
        Route::get('/summary', [MerchantWalletController::class, 'summary'])->middleware('permission:merchant_wallet.view');
        Route::get('/transactions', [MerchantWalletController::class, 'transactions'])->middleware('permission:merchant_wallet.view');
        Route::post('/deposit', [MerchantWalletController::class, 'deposit'])->middleware('permission:merchant_wallet.deposit');
        Route::post('/withdraw', [MerchantWalletController::class, 'withdraw'])->middleware('permission:merchant_wallet.withdraw');
    });

    Route::prefix('merchant-wallet-transactions')->group(function () {
        Route::get('/{transaction}', [MerchantWalletTransactionController::class, 'show'])->middleware('permission:merchant_wallet.view');
        Route::post('/{transaction}/approve', [MerchantWalletTransactionController::class, 'approve'])->middleware('permission:merchant_wallet.approve');
        Route::post('/{transaction}/reject', [MerchantWalletTransactionController::class, 'reject'])->middleware('permission:merchant_wallet.reject');
    });

    Route::prefix('treasury-accounts')->group(function () {
        Route::get('/', [TreasuryAccountController::class, 'index'])->middleware('permission:finance.treasury.view');
        Route::post('/', [TreasuryAccountController::class, 'store'])->middleware('permission:finance.treasury.create');
        Route::get('/{treasuryAccount}', [TreasuryAccountController::class, 'show'])->middleware('permission:finance.treasury.view');
        Route::put('/{treasuryAccount}', [TreasuryAccountController::class, 'update'])->middleware('permission:finance.treasury.update');
        Route::delete('/{treasuryAccount}', [TreasuryAccountController::class, 'destroy'])->middleware('permission:finance.treasury.delete');
    });

    Route::get('/treasury-transactions', [TreasuryTransactionController::class, 'index'])->middleware('permission:finance.treasury.view');
});
