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
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\Order\Enums\PaymentType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceSummaryService
{
    public function __construct(
        private readonly MerchantWalletService $merchantWalletService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function getMerchantAvailableBalance(Merchant $merchant, ?MerchantPayout $excludePayout = null): float
    {
        $approvedWalletTransactionCount = MerchantWalletTransaction::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->count();

        if ($approvedWalletTransactionCount > 0) {
            $availableBalance = $this->merchantWalletService->getAvailableBalance($merchant->id);

            if (
                $excludePayout !== null
                && $excludePayout->status === PayoutStatus::COMPLETED
                && $excludePayout->walletTransaction !== null
            ) {
                $availableBalance += (float) $excludePayout->walletTransaction->amount;
            }

            return $availableBalance;
        }

        return $this->legacyMerchantAvailableBalance($merchant, $excludePayout);
    }

    public function getDriverExpectedCash(Driver $driver): float
    {
        return Shipment::query()
            ->where('assigned_driver_id', $driver->id)
            ->financiallyCompleted()
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee'])
            ->sum(fn (Shipment $shipment): float => $this->shipmentDeliveredCodAmount($shipment));
    }

    /**
     * @return array<string, mixed>
     */
    public function getMerchantInvoicePreview(MerchantInvoice $invoice): array
    {
        $invoice->loadMissing(['merchant', 'items']);
        $merchant = $invoice->merchant()->firstOrFail();
        $currency = (string) $this->systemSettingService->get('default_currency', 'EGP');
        $dueDays = max(0, (int) $this->systemSettingService->get('invoice_due_days', 7));

        $shipments = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->financiallyCompleted()
            ->when($invoice->period_start !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $invoice->period_start))
            ->when($invoice->period_end !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $invoice->period_end))
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->orderBy('created_at')
            ->get(['id', 'order_id', 'shipment_number', 'customer_name', 'customer_phone', 'shipping_fee', 'created_at']);

        return [
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'phone' => $merchant->phone,
            ],
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'currency' => $currency !== '' ? $currency : 'EGP',
            'status' => $invoice->status,
            'period' => [
                'start' => $invoice->period_start?->format('Y-m-d'),
                'end' => $invoice->period_end?->format('Y-m-d'),
            ],
            'due_date' => ($invoice->issued_at ?? $invoice->created_at)?->copy()->addDays($dueDays)?->toDateString(),
            'totals' => [
                'total_cod' => $invoice->total_cod,
                'total_shipping_fees' => $invoice->total_shipping_fees,
                'total_warehouse_charges' => $invoice->total_warehouse_charges,
                'total_payable' => $invoice->total_payable,
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'total_amount' => $item->total_amount,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
            ])->values(),
            'warehouse_charges' => WarehouseCharge::query()
                ->where('merchant_id', $merchant->id)
                ->where('status', '!=', WarehouseChargeStatus::CANCELLED)
                ->when($invoice->period_start !== null, fn (Builder $query) => $query->whereDate('charge_date', '>=', $invoice->period_start))
                ->when($invoice->period_end !== null, fn (Builder $query) => $query->whereDate('charge_date', '<=', $invoice->period_end))
                ->orderBy('charge_date')
                ->get()
                ->map(fn (WarehouseCharge $charge) => [
                    'id' => $charge->id,
                    'type' => $charge->type,
                    'description' => $charge->description,
                    'quantity' => $charge->quantity,
                    'unit_price' => $charge->unit_price,
                    'amount' => $charge->amount,
                    'status' => $charge->status,
                    'charge_date' => $charge->charge_date,
                    'notes' => $charge->notes,
                ])
                ->values(),
            'shipments' => $shipments->map(fn ($shipment) => [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'customer_name' => $shipment->customer_name,
                'customer_phone' => $shipment->customer_phone,
                'payment_type' => $shipment->order?->payment_type,
                'cod_amount' => number_format($this->shipmentDeliveredCodAmount($shipment), 2, '.', ''),
                'shipping_fee' => $shipment->shipping_fee,
                'delivered_at' => $shipment->created_at,
            ])->values(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total_cod:string,total_shipping_fees:string,total_warehouse_charges:string,total_payable:string}
     */
    public function calculateMerchantInvoiceTotals(Merchant $merchant, array $filters): array
    {
        $approvedWalletTransactions = $this->approvedWalletTransactionsForMerchant($merchant, $filters);

        if ($approvedWalletTransactions->isNotEmpty()) {
            $totalCod = $approvedWalletTransactions
                ->where('transaction_type', MerchantWalletTransactionType::COD_COLLECTED)
                ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount);

            $shippingFeeTypes = [
                MerchantWalletTransactionType::SHIPPING_FEE,
                MerchantWalletTransactionType::RETURN_FEE,
                MerchantWalletTransactionType::SWAP_FEE,
            ];

            $warehouseFeeTypes = [
                MerchantWalletTransactionType::STORAGE_FEE,
                MerchantWalletTransactionType::PICK_FEE,
                MerchantWalletTransactionType::PACKING_FEE,
                MerchantWalletTransactionType::WAREHOUSE_CHARGE,
            ];

            $totalShippingFees = $approvedWalletTransactions
                ->whereIn('transaction_type', $shippingFeeTypes)
                ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount);

            $totalWarehouseCharges = $approvedWalletTransactions
                ->whereIn('transaction_type', $warehouseFeeTypes)
                ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount);

            $credits = $approvedWalletTransactions
                ->where('direction', MerchantWalletDirection::CREDIT)
                ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount);

            $debits = $approvedWalletTransactions
                ->where('direction', MerchantWalletDirection::DEBIT)
                ->sum(fn (MerchantWalletTransaction $transaction): float => (float) $transaction->amount);

            return [
                'total_cod' => number_format((float) $totalCod, 2, '.', ''),
                'total_shipping_fees' => number_format((float) $totalShippingFees, 2, '.', ''),
                'total_warehouse_charges' => number_format((float) $totalWarehouseCharges, 2, '.', ''),
                'total_payable' => number_format((float) ($credits - $debits), 2, '.', ''),
            ];
        }

        return $this->legacyMerchantInvoiceTotals($merchant, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getMerchantSummary(Merchant $merchant, array $filters): array
    {
        $completedShipmentQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->financiallyCompleted()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $completedShipments = (clone $completedShipmentQuery)
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $deliveredShipmentQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', ShipmentStatus::DELIVERED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $deliveredOnlyShipments = (clone $deliveredShipmentQuery)
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $codCollected = $completedShipments->sum(fn (Shipment $shipment): float => $this->shipmentDeliveredCodAmount($shipment));

        $codDeliveredCount = $deliveredOnlyShipments
            ->filter(fn ($shipment) => $shipment->order?->payment_type === PaymentType::COD)
            ->count();

        $prepaidDeliveredCount = $deliveredOnlyShipments
            ->filter(fn ($shipment) => $shipment->order?->payment_type === PaymentType::PREPAID)
            ->count();

        $shippingFees = $completedShipments->sum(fn ($shipment) => (float) $shipment->shipping_fee);
        $warehouseCharges = (float) WarehouseCharge::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', '!=', WarehouseChargeStatus::CANCELLED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('charge_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('charge_date', '<=', $filters['date_to']))
            ->sum('amount');

        $merchantPayable = $completedShipments->sum(fn (Shipment $shipment): float => $this->shipmentMerchantPayable($shipment));

        $payoutQuery = MerchantPayout::query()
            ->where('merchant_id', $merchant->id)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $paidOut = (float) (clone $payoutQuery)
            ->where('status', PayoutStatus::COMPLETED)
            ->sum('amount');

        $pendingPayouts = (float) (clone $payoutQuery)
            ->where('status', PayoutStatus::PENDING)
            ->sum('amount');

        $legacyRemainingBalance = $merchantPayable - $paidOut - $pendingPayouts;
        $walletSummary = $this->merchantWalletService->getLedgerSummary($merchant->id, $filters);
        $currency = (string) $this->systemSettingService->get('default_currency', 'EGP');

        return [
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'currency' => $currency !== '' ? $currency : 'EGP',
            'cod_collected' => number_format((float) $codCollected, 2, '.', ''),
            'shipping_fees' => number_format((float) $shippingFees, 2, '.', ''),
            'warehouse_charges' => number_format($warehouseCharges, 2, '.', ''),
            'merchant_payable' => number_format((float) $merchantPayable, 2, '.', ''),
            'paid_out' => number_format((float) $paidOut, 2, '.', ''),
            'pending_payouts' => number_format((float) $pendingPayouts, 2, '.', ''),
            'remaining_balance' => number_format($legacyRemainingBalance, 2, '.', ''),
            'legacy_remaining_balance' => number_format($legacyRemainingBalance, 2, '.', ''),
            'wallet_available_balance' => $walletSummary['available_balance'],
            'wallet_pending_credits' => $walletSummary['pending_credits'],
            'wallet_pending_debits' => $walletSummary['pending_debits'],
            'total_wallet_credits' => $walletSummary['total_wallet_credits'],
            'total_wallet_debits' => $walletSummary['total_wallet_debits'],
            'company_profit_from_shipping' => number_format((float) $shippingFees, 2, '.', ''),
            'shipments' => [
                'delivered_count' => (clone $deliveredShipmentQuery)->count(),
                'cod_delivered_count' => $codDeliveredCount,
                'prepaid_delivered_count' => $prepaidDeliveredCount,
            ],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getCompanyProfitSummary(array $filters): array
    {
        $completedShipmentQuery = Shipment::query()
            ->financiallyCompleted()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $completedShipments = (clone $completedShipmentQuery)
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $totalShippingFees = $completedShipments->sum(fn ($shipment) => (float) $shipment->shipping_fee);
        $totalWarehouseCharges = (float) WarehouseCharge::query()
            ->where('status', '!=', WarehouseChargeStatus::CANCELLED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('charge_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('charge_date', '<=', $filters['date_to']))
            ->sum('amount');
        $totalCodCollected = $completedShipments->sum(fn (Shipment $shipment): float => $this->shipmentDeliveredCodAmount($shipment));

        $deliveredShipmentsCount = Shipment::query()
            ->where('status', ShipmentStatus::DELIVERED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->count();

        $expenseQuery = Expense::query()
            ->where('status', ExpenseStatus::PAID)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('expense_date', '<=', $filters['date_to']));

        $totalExpenses = (float) $expenseQuery->sum('amount');
        $currency = (string) $this->systemSettingService->get('default_currency', 'EGP');

        return [
            'currency' => $currency !== '' ? $currency : 'EGP',
            'total_shipping_fees' => number_format((float) $totalShippingFees, 2, '.', ''),
            'total_warehouse_charges' => number_format($totalWarehouseCharges, 2, '.', ''),
            'total_expenses' => number_format($totalExpenses, 2, '.', ''),
            'net_company_profit' => number_format((float) $totalShippingFees + $totalWarehouseCharges - $totalExpenses, 2, '.', ''),
            'total_cod_collected' => number_format((float) $totalCodCollected, 2, '.', ''),
            'delivered_shipments_count' => $deliveredShipmentsCount,
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getReconciliationSummary(array $filters): array
    {
        $shipmentQuery = Shipment::query()
            ->financiallyCompleted()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $deliveredShipments = (clone $shipmentQuery)
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $totalCodExpected = $deliveredShipments->sum(fn (Shipment $shipment): float => $this->shipmentDeliveredCodAmount($shipment));
        $totalMerchantPayable = $deliveredShipments->sum(fn (Shipment $shipment): float => $this->shipmentMerchantPayable($shipment));

        $driverCashVerified = (float) DriverCashClosure::query()
            ->where('status', DriverCashClosureStatus::VERIFIED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('closure_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('closure_date', '<=', $filters['date_to']))
            ->sum('received_amount');

        $closedManifestQuery = DriverManifest::query()
            ->where('status', 'closed')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('manifest_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('manifest_date', '<=', $filters['date_to']));

        $manifestExpected = (float) (clone $closedManifestQuery)->sum('expected_cod_amount');
        $manifestActual = (float) (clone $closedManifestQuery)->sum('actual_received_amount');
        $manifestDifference = (float) (clone $closedManifestQuery)->sum('difference_amount');
        $manifestCount = (clone $closedManifestQuery)->count();

        $merchantPayoutQuery = MerchantPayout::query()
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $merchantPaidOut = (float) (clone $merchantPayoutQuery)
            ->where('status', PayoutStatus::COMPLETED)
            ->sum('amount');

        $walletApprovedQuery = MerchantWalletTransaction::query()
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));

        $walletCredits = (float) (clone $walletApprovedQuery)
            ->where('direction', MerchantWalletDirection::CREDIT)
            ->sum('amount');
        $walletDebits = (float) (clone $walletApprovedQuery)
            ->where('direction', MerchantWalletDirection::DEBIT)
            ->sum('amount');

        $treasuryBalanceTotal = (float) TreasuryAccount::query()->sum('current_balance');
        $treasuryAccountsTotal = TreasuryAccount::query()->count();
        $currency = (string) $this->systemSettingService->get('default_currency', 'EGP');

        return [
            'currency' => $currency !== '' ? $currency : 'EGP',
            'total_cod_expected' => number_format((float) $totalCodExpected, 2, '.', ''),
            'total_driver_cash_verified' => number_format($driverCashVerified, 2, '.', ''),
            'cod_difference' => number_format($driverCashVerified - (float) $totalCodExpected, 2, '.', ''),
            'driver_manifests_closed' => $manifestCount,
            'driver_manifest_expected_cod' => number_format($manifestExpected, 2, '.', ''),
            'driver_manifest_actual_received' => number_format($manifestActual, 2, '.', ''),
            'driver_manifest_discrepancy_total' => number_format($manifestDifference, 2, '.', ''),
            'wallet_total_credits' => number_format($walletCredits, 2, '.', ''),
            'wallet_total_debits' => number_format($walletDebits, 2, '.', ''),
            'wallet_available_total' => number_format($walletCredits - $walletDebits, 2, '.', ''),
            'payout_pending_total' => number_format((float) (clone $merchantPayoutQuery)->where('status', PayoutStatus::PENDING)->sum('amount'), 2, '.', ''),
            'payout_approved_total' => number_format((float) (clone $merchantPayoutQuery)->where('status', PayoutStatus::APPROVED)->sum('amount'), 2, '.', ''),
            'payout_completed_total' => number_format((float) (clone $merchantPayoutQuery)->where('status', PayoutStatus::COMPLETED)->sum('amount'), 2, '.', ''),
            'treasury_accounts_total' => $treasuryAccountsTotal,
            'treasury_current_balance_total' => number_format($treasuryBalanceTotal, 2, '.', ''),
            'total_merchant_payable' => number_format((float) $totalMerchantPayable, 2, '.', ''),
            'total_merchant_paid_out' => number_format($merchantPaidOut, 2, '.', ''),
            'merchant_balance_remaining' => number_format((float) $totalMerchantPayable - $merchantPaidOut, 2, '.', ''),
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ];
    }

    private function shipmentDeliveredCodAmount(Shipment $shipment): float
    {
        if ($shipment->order?->payment_type !== PaymentType::COD) {
            return 0;
        }

        return (float) $shipment->delivered_cod_amount;
    }

    private function shipmentMerchantPayable(Shipment $shipment): float
    {
        $codAmount = $this->shipmentDeliveredCodAmount($shipment);
        $shippingFee = (float) $shipment->shipping_fee;

        if ($codAmount <= 0) {
            return 0;
        }

        return max($codAmount - $shippingFee, 0);
    }

    private function legacyMerchantAvailableBalance(Merchant $merchant, ?MerchantPayout $excludePayout = null): float
    {
        $shipmentQuery = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->financiallyCompleted()
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ]);

        $deliveredShipments = $shipmentQuery->get(['cod_amount', 'shipping_fee']);

        $merchantPayable = $deliveredShipments->sum(function ($shipment): float {
            return $this->shipmentMerchantPayable($shipment);
        });

        $payoutQuery = MerchantPayout::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', [PayoutStatus::COMPLETED, PayoutStatus::PENDING]);

        if ($excludePayout !== null) {
            $payoutQuery->whereKeyNot($excludePayout->id);
        }

        $reservedPayouts = (float) $payoutQuery->sum('amount');

        return $merchantPayable - $reservedPayouts;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total_cod:string,total_shipping_fees:string,total_warehouse_charges:string,total_payable:string}
     */
    private function legacyMerchantInvoiceTotals(Merchant $merchant, array $filters): array
    {
        $shipments = Shipment::query()
            ->where('merchant_id', $merchant->id)
            ->financiallyCompleted()
            ->when(isset($filters['date_from']) && $filters['date_from'] !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']) && $filters['date_to'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->with([
                'order:id,payment_type',
                'order.items:id,order_id,delivered_quantity,unit_price',
            ])
            ->get(['id', 'order_id', 'shipping_fee']);

        $totalCod = $shipments->sum(fn (Shipment $shipment): float => $this->shipmentDeliveredCodAmount($shipment));
        $totalShippingFees = $shipments->sum(fn ($shipment) => (float) $shipment->shipping_fee);
        $warehouseCharges = WarehouseCharge::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', '!=', WarehouseChargeStatus::CANCELLED)
            ->when(isset($filters['date_from']) && $filters['date_from'] !== null, fn (Builder $query) => $query->whereDate('charge_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']) && $filters['date_to'] !== null, fn (Builder $query) => $query->whereDate('charge_date', '<=', $filters['date_to']))
            ->sum('amount');

        $totalPayable = $shipments->sum(fn (Shipment $shipment): float => $this->shipmentMerchantPayable($shipment));

        return [
            'total_cod' => number_format((float) $totalCod, 2, '.', ''),
            'total_shipping_fees' => number_format((float) $totalShippingFees, 2, '.', ''),
            'total_warehouse_charges' => number_format((float) $warehouseCharges, 2, '.', ''),
            'total_payable' => number_format((float) $totalPayable, 2, '.', ''),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<int, MerchantWalletTransaction>
     */
    private function approvedWalletTransactionsForMerchant(Merchant $merchant, array $filters): Collection
    {
        return MerchantWalletTransaction::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->orderBy('id')
            ->get();
    }
}
