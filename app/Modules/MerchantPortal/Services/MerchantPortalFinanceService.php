<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Finance\Enums\MerchantInvoiceStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Services\FinanceSummaryService;
use App\Modules\Finance\Services\MerchantWalletService;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class MerchantPortalFinanceService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly MerchantWalletService $merchantWalletService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function financeSummary(Merchant $merchant, array $filters = []): array
    {
        $merchantSummary = $this->financeSummaryService->getMerchantSummary($merchant, $filters);
        $walletSummary = $this->merchantWalletService->getLedgerSummary($merchant->id, $filters);

        $invoiceQuery = $this->invoiceQueryForMerchant($merchant->id, $filters);
        $payoutQuery = $this->payoutQueryForMerchant($merchant->id, $filters);

        $latestInvoice = (clone $invoiceQuery)->first();
        $lastPayout = (clone $payoutQuery)->first();
        $dateFilteredChargesQuery = WarehouseCharge::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', '!=', WarehouseChargeStatus::CANCELLED)
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('charge_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('charge_date', '<=', $filters['date_to']));

        return [
            'merchant_id' => $merchant->id,
            'available_balance' => $walletSummary['available_balance'],
            'pending_balance' => number_format(
                (float) $walletSummary['pending_credits'] - (float) $walletSummary['pending_debits'],
                2,
                '.',
                ''
            ),
            'total_cod_collected' => $merchantSummary['cod_collected'],
            'total_shipping_fees' => $merchantSummary['shipping_fees'],
            'total_warehouse_charges' => number_format((float) (clone $dateFilteredChargesQuery)->sum('amount'), 2, '.', ''),
            'total_payouts_pending' => number_format((float) (clone $payoutQuery)->where('status', 'pending')->sum('amount'), 2, '.', ''),
            'total_payouts_completed' => number_format((float) (clone $payoutQuery)->where('status', 'completed')->sum('amount'), 2, '.', ''),
            'total_invoices' => (clone $invoiceQuery)->count(),
            'unpaid_invoices_count' => (clone $invoiceQuery)
                ->whereNotIn('status', [MerchantInvoiceStatus::PAID, MerchantInvoiceStatus::CANCELLED])
                ->count(),
            'pending_payouts_count' => (clone $payoutQuery)->where('status', 'pending')->count(),
            'last_payout' => $lastPayout !== null ? [
                'id' => $lastPayout->id,
                'status' => $lastPayout->status,
                'amount' => $lastPayout->amount,
                'requested_at' => $lastPayout->created_at,
            ] : null,
            'latest_invoice' => $latestInvoice !== null ? [
                'id' => $latestInvoice->id,
                'invoice_number' => $latestInvoice->invoice_number,
                'status' => $latestInvoice->status,
                'total' => $latestInvoice->total_payable,
                'issued_at' => $latestInvoice->issued_at,
            ] : null,
            'currency' => $merchantSummary['currency'],
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
    public function walletSummary(Merchant $merchant, array $filters = []): array
    {
        $ledgerSummary = $this->merchantWalletService->getLedgerSummary($merchant->id, $filters);
        $lastTransaction = $this->walletTransactionQueryForMerchant($merchant->id, $filters)->first();
        $completedPayoutAmount = (float) $this->payoutQueryForMerchant($merchant->id, $filters)
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'merchant_id' => $merchant->id,
            'available_balance' => $ledgerSummary['available_balance'],
            'pending_balance' => number_format(
                (float) $ledgerSummary['pending_credits'] - (float) $ledgerSummary['pending_debits'],
                2,
                '.',
                ''
            ),
            'paid_amount' => number_format($completedPayoutAmount, 2, '.', ''),
            'total_credits' => $ledgerSummary['total_wallet_credits'],
            'total_debits' => $ledgerSummary['total_wallet_debits'],
            'last_transaction_at' => $lastTransaction?->created_at,
            'currency' => (string) $this->systemSettingService->get('default_currency', 'EGP'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<MerchantWalletTransaction>
     */
    public function walletTransactionQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return $this->merchantWalletService->ledgerQuery($merchantId, [
            'transaction_type' => $filters['type'] ?? null,
            'direction' => $filters['direction'] ?? null,
            'status' => $filters['status'] ?? null,
            'source_type' => $filters['reference_type'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ])
            ->with(['merchant'])
            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('transaction_number', 'like', '%'.$search.'%')
                        ->orWhere('reference_number', 'like', '%'.$search.'%')
                        ->orWhere('reason', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwnedWalletTransaction(int $merchantId, int $transactionId): MerchantWalletTransaction
    {
        return $this->walletTransactionQueryForMerchant($merchantId)
            ->whereKey($transactionId)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<MerchantInvoice>
     */
    public function invoiceQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return MerchantInvoice::query()
            ->with(['items'])
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['invoice_number']), fn (Builder $query) => $query->where('invoice_number', 'like', '%'.$filters['invoice_number'].'%'))
            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwnedInvoice(int $merchantId, int $invoiceId): MerchantInvoice
    {
        $invoice = $this->invoiceQueryForMerchant($merchantId)
            ->whereKey($invoiceId)
            ->firstOrFail();

        return $this->decorateInvoice($invoice);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<MerchantPayout>
     */
    public function payoutQueryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return MerchantPayout::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['payout_number']), fn (Builder $query) => $query->where('reference_number', 'like', '%'.$filters['payout_number'].'%'))
            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('reference_number', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhere('payment_method', 'like', '%'.$search.'%');
                });
            })
            ->latest();
    }

    public function findOwnedPayout(int $merchantId, int $payoutId): MerchantPayout
    {
        return $this->payoutQueryForMerchant($merchantId)
            ->whereKey($payoutId)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function requestPayout(Merchant $merchant, array $validated, ?int $userId): MerchantPayout
    {
        $amount = (float) $validated['amount'];
        $availableBalance = $this->financeSummaryService->getMerchantAvailableBalance($merchant);

        if ($amount > $availableBalance) {
            $this->fail('Payout amount exceeds merchant available balance.');
        }

        return DB::transaction(function () use ($merchant, $validated, $userId) {
            $paymentMethod = $validated['payment_method'] ?? $validated['method'] ?? null;
            $notes = $this->buildPayoutNotes($validated);

            $payout = MerchantPayout::create([
                'merchant_id' => $merchant->id,
                'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->auditLogService->log(
                AuditEventType::PAYOUT_CREATED,
                'created',
                $payout,
                null,
                [
                    'user_id' => $userId,
                    'metadata' => [
                        'merchant_id' => $merchant->id,
                        'payment_method' => $paymentMethod,
                        'reference_number' => $validated['reference_number'] ?? null,
                        'source' => 'merchant_portal',
                    ],
                    'notes' => 'Merchant portal payout requested.',
                ],
            );

            return $payout;
        });
    }

    private function decorateInvoice(MerchantInvoice $invoice): MerchantInvoice
    {
        $currency = (string) $this->systemSettingService->get('default_currency', 'EGP');
        $dueDays = max(0, (int) $this->systemSettingService->get('invoice_due_days', 7));
        $dueDateBase = $invoice->issued_at ?? $invoice->created_at;

        $invoice->setAttribute('currency', $currency !== '' ? $currency : 'EGP');
        $invoice->setAttribute('due_date', $dueDateBase?->copy()->addDays($dueDays)?->toDateString());

        return $invoice;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildPayoutNotes(array $validated): ?string
    {
        $notes = trim((string) ($validated['notes'] ?? ''));
        $bankParts = [];

        if (! empty($validated['bank_name'])) {
            $bankParts[] = 'Bank: '.$validated['bank_name'];
        }

        if (! empty($validated['bank_account_name'])) {
            $bankParts[] = 'Account Name: '.$validated['bank_account_name'];
        }

        if (! empty($validated['bank_account_number'])) {
            $bankParts[] = 'Account Number: '.$validated['bank_account_number'];
        }

        $bankDetails = implode(' | ', $bankParts);

        if ($notes !== '' && $bankDetails !== '') {
            return $notes.PHP_EOL.$bankDetails;
        }

        if ($notes !== '') {
            return $notes;
        }

        return $bankDetails !== '' ? $bankDetails : null;
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
