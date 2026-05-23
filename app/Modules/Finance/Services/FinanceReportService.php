<?php

namespace App\Modules\Finance\Services;

use App\Models\Merchant;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Finance\Enums\DriverCashClosureStatus;
use App\Modules\Finance\Enums\ExpenseStatus;
use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionType;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Models\DriverCashClosure;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\Finance\Models\TreasuryTransaction;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

class FinanceReportService
{
    public function __construct(
        private readonly FinanceSummaryService $financeSummaryService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getOverviewReport(array $filters): array
    {
        $companyProfit = $this->financeSummaryService->getCompanyProfitSummary($filters);
        $reconciliation = $this->financeSummaryService->getReconciliationSummary($filters);

        $shipmentQuery = Shipment::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['driver_id']), fn (Builder $query) => $query->where('assigned_driver_id', $filters['driver_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));

        $orderQuery = Order::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']));

        return [
            'total_orders' => $orderQuery->count(),
            'total_shipments' => (clone $shipmentQuery)->count(),
            'delivered_shipments' => (clone $shipmentQuery)->where('status', ShipmentStatus::DELIVERED)->count(),
            'cod_expected' => $reconciliation['total_cod_expected'],
            'cod_collected_verified' => $reconciliation['total_driver_cash_verified'],
            'shipping_fees' => $companyProfit['total_shipping_fees'],
            'warehouse_charges' => $companyProfit['total_warehouse_charges'],
            'merchant_payables' => $reconciliation['total_merchant_payable'],
            'merchant_paid_out' => $reconciliation['total_merchant_paid_out'],
            'merchant_remaining_balance' => $reconciliation['merchant_balance_remaining'],
            'expenses' => $companyProfit['total_expenses'],
            'company_net_profit' => $companyProfit['net_company_profit'],
            'wallet_available_total' => $reconciliation['wallet_available_total'],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'merchant_id' => $filters['merchant_id'] ?? null,
                'driver_id' => $filters['driver_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getMerchantReport(Merchant $merchant, array $filters): array
    {
        $summary = $this->financeSummaryService->getMerchantSummary($merchant, $filters);
        $shipmentQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));

        return [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'shipments' => (clone $shipmentQuery)->count(),
            'delivered' => $summary['shipments']['delivered_count'],
            'cod' => $summary['cod_collected'],
            'shipping' => $summary['shipping_fees'],
            'warehouse_charges' => $summary['warehouse_charges'],
            'payable' => $summary['merchant_payable'],
            'paid_out' => $summary['paid_out'],
            'remaining' => $summary['remaining_balance'],
            'wallet_available_balance' => $summary['wallet_available_balance'],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDriversReport(array $filters): array
    {
        $drivers = Driver::query()
            ->when(isset($filters['driver_id']), fn (Builder $query) => $query->whereKey($filters['driver_id']))
            ->latest()
            ->get();

        $items = $drivers->map(function (Driver $driver) use ($filters): array {
            $shipmentQuery = Shipment::query()
                ->where('assigned_driver_id', $driver->id)
                ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
                ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
                ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));

            $expectedCod = (float) (clone $shipmentQuery)
                ->financiallyCompleted()
                ->sum('cod_amount');

            $receivedCod = (float) DriverCashClosure::query()
                ->where('driver_id', $driver->id)
                ->where('status', DriverCashClosureStatus::VERIFIED)
                ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('closure_date', '>=', $filters['date_from']))
                ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('closure_date', '<=', $filters['date_to']))
                ->sum('received_amount');

            $closedManifestQuery = DriverManifest::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'closed')
                ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('manifest_date', '>=', $filters['date_from']))
                ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('manifest_date', '<=', $filters['date_to']));

            $manifestDiscrepancy = (float) (clone $closedManifestQuery)->sum('difference_amount');

            return [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'shipments' => (clone $shipmentQuery)->count(),
                'delivered' => (clone $shipmentQuery)->where('status', ShipmentStatus::DELIVERED)->count(),
                'closed_manifests' => (clone $closedManifestQuery)->count(),
                'expected_cod' => number_format($expectedCod, 2, '.', ''),
                'received_cod' => number_format($receivedCod, 2, '.', ''),
                'differences' => number_format($receivedCod - $expectedCod, 2, '.', ''),
                'manifest_discrepancy' => number_format($manifestDiscrepancy, 2, '.', ''),
            ];
        })->values();

        return [
            'items' => $items,
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'driver_id' => $filters['driver_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getExpensesReport(array $filters): array
    {
        $query = Expense::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('expense_date', '<=', $filters['date_to']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('category', $filters['category']));

        $expenses = $query->get(['category', 'amount']);

        return [
            'total' => number_format((float) $expenses->sum(fn ($expense) => (float) $expense->amount), 2, '.', ''),
            'by_category' => $expenses
                ->groupBy('category')
                ->map(fn ($group, $category) => [
                    'category' => $category,
                    'amount' => number_format((float) $group->sum(fn ($expense) => (float) $expense->amount), 2, '.', ''),
                ])
                ->values(),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getPayoutsReport(array $filters): array
    {
        $query = MerchantPayout::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']));

        return [
            'total_payouts' => number_format((float) (clone $query)->sum('amount'), 2, '.', ''),
            'pending' => number_format((float) (clone $query)->where('status', PayoutStatus::PENDING)->sum('amount'), 2, '.', ''),
            'approved' => number_format((float) (clone $query)->where('status', PayoutStatus::APPROVED)->sum('amount'), 2, '.', ''),
            'completed' => number_format((float) (clone $query)->where('status', PayoutStatus::COMPLETED)->sum('amount'), 2, '.', ''),
            'rejected' => number_format((float) (clone $query)->where('status', PayoutStatus::REJECTED)->sum('amount'), 2, '.', ''),
            'cancelled' => number_format((float) (clone $query)->where('status', PayoutStatus::CANCELLED)->sum('amount'), 2, '.', ''),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'merchant_id' => $filters['merchant_id'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDailyCashReport(array $filters): array
    {
        $query = TreasuryTransaction::query()
            ->with('treasuryAccount')
            ->when(isset($filters['treasury_account_id']), fn (Builder $q) => $q->where('treasury_account_id', $filters['treasury_account_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']));

        $credits = (float) (clone $query)->where('direction', MerchantWalletDirection::CREDIT)->sum('amount');
        $debits = (float) (clone $query)->where('direction', MerchantWalletDirection::DEBIT)->sum('amount');

        return [
            'total_cash_in' => number_format($credits, 2, '.', ''),
            'total_cash_out' => number_format($debits, 2, '.', ''),
            'net_cash' => number_format($credits - $debits, 2, '.', ''),
            'driver_cash_received' => number_format((float) (clone $query)->where('source_type', 'driver_cash_closure')->sum('amount'), 2, '.', ''),
            'merchant_payouts_paid' => number_format((float) (clone $query)->where('source_type', 'merchant_payout')->sum('amount'), 2, '.', ''),
            'expenses_paid' => number_format((float) (clone $query)->where('source_type', 'expense')->sum('amount'), 2, '.', ''),
            'treasury_accounts' => TreasuryAccount::query()
                ->when(isset($filters['treasury_account_id']), fn (Builder $q) => $q->whereKey($filters['treasury_account_id']))
                ->get()
                ->map(fn (TreasuryAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'code' => $account->code,
                    'type' => $account->type,
                    'current_balance' => $account->current_balance,
                ])
                ->values(),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'treasury_account_id' => $filters['treasury_account_id'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getMerchantLedgerReport(array $filters): array
    {
        $query = MerchantWalletTransaction::query()
            ->with(['merchant', 'createdBy', 'approvedBy', 'rejectedBy'])
            ->when(isset($filters['merchant_id']), fn (Builder $q) => $q->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->latest();

        $transactions = $query->get();

        return [
            'items' => $transactions->map(fn (MerchantWalletTransaction $transaction) => [
                'id' => $transaction->id,
                'merchant_id' => $transaction->merchant_id,
                'merchant_name' => $transaction->merchant?->name,
                'transaction_number' => $transaction->transaction_number,
                'transaction_type' => $transaction->transaction_type,
                'direction' => $transaction->direction,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'status' => $transaction->status,
                'source_type' => $transaction->source_type,
                'source_id' => $transaction->source_id,
                'reference_number' => $transaction->reference_number,
                'created_at' => $transaction->created_at,
            ])->values(),
            'summary' => [
                'total_credits' => number_format((float) $transactions
                    ->where('status', MerchantWalletTransactionStatus::APPROVED)
                    ->where('direction', MerchantWalletDirection::CREDIT)
                    ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount), 2, '.', ''),
                'total_debits' => number_format((float) $transactions
                    ->where('status', MerchantWalletTransactionStatus::APPROVED)
                    ->where('direction', MerchantWalletDirection::DEBIT)
                    ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount), 2, '.', ''),
                'pending_transactions' => $transactions
                    ->where('status', MerchantWalletTransactionStatus::PENDING)
                    ->count(),
            ],
            'filters' => [
                'merchant_id' => $filters['merchant_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getDriverDiscrepanciesReport(array $filters): array
    {
        $query = DriverManifest::query()
            ->with('driver')
            ->where('status', 'closed')
            ->when(isset($filters['driver_id']), fn (Builder $q) => $q->where('driver_id', $filters['driver_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('manifest_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('manifest_date', '<=', $filters['date_to']))
            ->latest('manifest_date');

        $manifests = $query->get();

        return [
            'items' => $manifests->map(fn (DriverManifest $manifest) => [
                'manifest_id' => $manifest->id,
                'manifest_number' => $manifest->manifest_number,
                'manifest_date' => $manifest->manifest_date,
                'driver_id' => $manifest->driver_id,
                'driver_name' => $manifest->driver?->name,
                'expected_cod_amount' => $manifest->expected_cod_amount,
                'actual_received_amount' => $manifest->actual_received_amount,
                'difference_amount' => $manifest->difference_amount,
                'total_shipments' => $manifest->total_shipments,
            ])->values(),
            'summary' => [
                'closed_manifests' => $manifests->count(),
                'expected_cod_amount' => number_format((float) $manifests->sum(fn (DriverManifest $manifest): float => (float) $manifest->expected_cod_amount), 2, '.', ''),
                'actual_received_amount' => number_format((float) $manifests->sum(fn (DriverManifest $manifest): float => (float) $manifest->actual_received_amount), 2, '.', ''),
                'difference_amount' => number_format((float) $manifests->sum(fn (DriverManifest $manifest): float => (float) $manifest->difference_amount), 2, '.', ''),
            ],
            'filters' => [
                'driver_id' => $filters['driver_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getWarehouseRevenueReport(array $filters): array
    {
        $warehouseTypes = [
            MerchantWalletTransactionType::STORAGE_FEE,
            MerchantWalletTransactionType::PICK_FEE,
            MerchantWalletTransactionType::PACKING_FEE,
            MerchantWalletTransactionType::WAREHOUSE_CHARGE,
        ];

        $query = MerchantWalletTransaction::query()
            ->with('merchant')
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->whereIn('transaction_type', $warehouseTypes)
            ->when(isset($filters['merchant_id']), fn (Builder $q) => $q->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']));

        $transactions = $query->get();

        return [
            'total_revenue' => number_format((float) $transactions->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount), 2, '.', ''),
            'by_type' => $transactions
                ->groupBy('transaction_type')
                ->map(fn ($group, $type) => [
                    'transaction_type' => $type,
                    'amount' => number_format((float) $group->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount), 2, '.', ''),
                ])
                ->values(),
            'by_merchant' => $transactions
                ->groupBy('merchant_id')
                ->map(fn ($group) => [
                    'merchant_id' => $group->first()?->merchant_id,
                    'merchant_name' => $group->first()?->merchant?->name,
                    'amount' => number_format((float) $group->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount), 2, '.', ''),
                ])
                ->values(),
            'filters' => [
                'merchant_id' => $filters['merchant_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getProfitLossReport(array $filters): array
    {
        $shippingRevenueTypes = [
            MerchantWalletTransactionType::SHIPPING_FEE,
            MerchantWalletTransactionType::RETURN_FEE,
            MerchantWalletTransactionType::SWAP_FEE,
        ];

        $warehouseRevenueTypes = [
            MerchantWalletTransactionType::STORAGE_FEE,
            MerchantWalletTransactionType::PICK_FEE,
            MerchantWalletTransactionType::PACKING_FEE,
            MerchantWalletTransactionType::WAREHOUSE_CHARGE,
        ];

        $revenueQuery = MerchantWalletTransaction::query()
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->where('direction', MerchantWalletDirection::DEBIT)
            ->when(isset($filters['merchant_id']), fn (Builder $q) => $q->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']));

        $shippingRevenue = (float) (clone $revenueQuery)
            ->whereIn('transaction_type', $shippingRevenueTypes)
            ->sum('amount');
        $warehouseRevenue = (float) (clone $revenueQuery)
            ->whereIn('transaction_type', $warehouseRevenueTypes)
            ->sum('amount');

        $expenseQuery = Expense::query()
            ->where('status', ExpenseStatus::PAID)
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('expense_date', '<=', $filters['date_to']));

        $expenses = (float) $expenseQuery->sum('amount');

        $treasuryQuery = TreasuryTransaction::query()
            ->when(isset($filters['treasury_account_id']), fn (Builder $q) => $q->where('treasury_account_id', $filters['treasury_account_id']))
            ->when(isset($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']));

        $cashIn = (float) (clone $treasuryQuery)->where('direction', MerchantWalletDirection::CREDIT)->sum('amount');
        $cashOut = (float) (clone $treasuryQuery)->where('direction', MerchantWalletDirection::DEBIT)->sum('amount');

        return [
            'shipping_revenue' => number_format($shippingRevenue, 2, '.', ''),
            'warehouse_revenue' => number_format($warehouseRevenue, 2, '.', ''),
            'total_revenue' => number_format($shippingRevenue + $warehouseRevenue, 2, '.', ''),
            'paid_expenses' => number_format($expenses, 2, '.', ''),
            'net_profit' => number_format($shippingRevenue + $warehouseRevenue - $expenses, 2, '.', ''),
            'treasury_cash_in' => number_format($cashIn, 2, '.', ''),
            'treasury_cash_out' => number_format($cashOut, 2, '.', ''),
            'treasury_net_cash' => number_format($cashIn - $cashOut, 2, '.', ''),
            'filters' => [
                'merchant_id' => $filters['merchant_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'treasury_account_id' => $filters['treasury_account_id'] ?? null,
            ],
        ];
    }
}
