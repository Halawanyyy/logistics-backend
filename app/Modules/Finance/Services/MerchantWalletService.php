<?php

namespace App\Modules\Finance\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionType;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Shipment\Enums\ShipmentStatus;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Enums\WarehouseChargeStatus;
use App\Modules\Warehouse\Enums\WarehouseChargeType;
use App\Modules\Warehouse\Models\WarehouseCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class MerchantWalletService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTransaction(array $data): MerchantWalletTransaction
    {
        return DB::transaction(function () use ($data) {
            $existing = $this->findExistingTransaction($data);

            if ($existing !== null) {
                return $existing->load($this->relations());
            }

            $status = $data['status'] ?? MerchantWalletTransactionStatus::APPROVED;
            $amount = (float) $data['amount'];
            $direction = $data['direction'];
            $createdBy = $data['created_by'] ?? null;
            $approvedBy = $data['approved_by'] ?? $createdBy;
            $approvedAt = $data['approved_at'] ?? now();

            $payload = [
                'merchant_id' => $data['merchant_id'],
                'transaction_number' => $this->generateTransactionNumber(),
                'transaction_type' => $data['transaction_type'],
                'direction' => $direction,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => $status,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'created_by' => $createdBy,
            ];

            if ($status === MerchantWalletTransactionStatus::APPROVED) {
                $payload['balance_after'] = number_format(
                    $this->nextApprovedBalance((int) $data['merchant_id'], $amount, $direction),
                    2,
                    '.',
                    ''
                );
                $payload['approved_by'] = $approvedBy;
                $payload['approved_at'] = $approvedAt;
            }

            if ($status === MerchantWalletTransactionStatus::REJECTED) {
                $payload['rejected_by'] = $data['rejected_by'] ?? $createdBy;
                $payload['rejected_at'] = $data['rejected_at'] ?? now();
                $payload['rejection_reason'] = $data['rejection_reason'] ?? 'Rejected on creation.';
            }

            return MerchantWalletTransaction::create($payload)->load($this->relations());
        });
    }

    public function approveTransaction(MerchantWalletTransaction $transaction, ?int $approvedBy): MerchantWalletTransaction
    {
        return DB::transaction(function () use ($transaction, $approvedBy) {
            $transaction = MerchantWalletTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($transaction->status !== MerchantWalletTransactionStatus::PENDING) {
                $this->fail('Only pending wallet transactions can be approved.');
            }

            $amount = (float) $transaction->amount;
            $balanceAfter = $this->nextApprovedBalance(
                (int) $transaction->merchant_id,
                $amount,
                (string) $transaction->direction
            );

            $transaction->update([
                'status' => MerchantWalletTransactionStatus::APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'balance_after' => number_format($balanceAfter, 2, '.', ''),
            ]);

            $this->auditLogService->logStatusChange(
                $transaction->fresh(),
                AuditEventType::WALLET_TRANSACTION_APPROVED,
                MerchantWalletTransactionStatus::PENDING,
                MerchantWalletTransactionStatus::APPROVED,
                [
                    'user_id' => $approvedBy,
                    'metadata' => [
                        'balance_after' => number_format($balanceAfter, 2, '.', ''),
                    ],
                ],
                'Wallet transaction approved.',
                null,
                'approved',
            );

            return $transaction->fresh()->load($this->relations());
        });
    }

    public function rejectTransaction(MerchantWalletTransaction $transaction, string $rejectionReason, ?int $rejectedBy): MerchantWalletTransaction
    {
        return DB::transaction(function () use ($transaction, $rejectionReason, $rejectedBy) {
            $transaction = MerchantWalletTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($transaction->status !== MerchantWalletTransactionStatus::PENDING) {
                $this->fail('Only pending wallet transactions can be rejected.');
            }

            $transaction->update([
                'status' => MerchantWalletTransactionStatus::REJECTED,
                'rejected_by' => $rejectedBy,
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            $this->auditLogService->logStatusChange(
                $transaction->fresh(),
                AuditEventType::WALLET_TRANSACTION_REJECTED,
                MerchantWalletTransactionStatus::PENDING,
                MerchantWalletTransactionStatus::REJECTED,
                [
                    'user_id' => $rejectedBy,
                    'metadata' => [
                        'rejection_reason' => $rejectionReason,
                    ],
                ],
                'Wallet transaction rejected.',
                null,
                'rejected',
            );

            return $transaction->fresh()->load($this->relations());
        });
    }

    public function getAvailableBalance(int $merchantId): float
    {
        $credits = (float) MerchantWalletTransaction::query()
            ->where('merchant_id', $merchantId)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->where('direction', MerchantWalletDirection::CREDIT)
            ->sum('amount');

        $debits = (float) MerchantWalletTransaction::query()
            ->where('merchant_id', $merchantId)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->where('direction', MerchantWalletDirection::DEBIT)
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * @return array{credits: float, debits: float}
     */
    public function getPendingBalance(int $merchantId): array
    {
        return [
            'credits' => (float) MerchantWalletTransaction::query()
                ->where('merchant_id', $merchantId)
                ->where('status', MerchantWalletTransactionStatus::PENDING)
                ->where('direction', MerchantWalletDirection::CREDIT)
                ->sum('amount'),
            'debits' => (float) MerchantWalletTransaction::query()
                ->where('merchant_id', $merchantId)
                ->where('status', MerchantWalletTransactionStatus::PENDING)
                ->where('direction', MerchantWalletDirection::DEBIT)
                ->sum('amount'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string>
     */
    public function getLedgerSummary(int $merchantId, array $filters = []): array
    {
        $approvedQuery = $this->ledgerQuery($merchantId, array_merge($filters, [
            'status' => MerchantWalletTransactionStatus::APPROVED,
        ]));
        $pendingQuery = $this->ledgerQuery($merchantId, array_merge($filters, [
            'status' => MerchantWalletTransactionStatus::PENDING,
        ]));

        $approvedCredits = (float) (clone $approvedQuery)
            ->where('direction', MerchantWalletDirection::CREDIT)
            ->sum('amount');
        $approvedDebits = (float) (clone $approvedQuery)
            ->where('direction', MerchantWalletDirection::DEBIT)
            ->sum('amount');
        $pendingCredits = (float) (clone $pendingQuery)
            ->where('direction', MerchantWalletDirection::CREDIT)
            ->sum('amount');
        $pendingDebits = (float) (clone $pendingQuery)
            ->where('direction', MerchantWalletDirection::DEBIT)
            ->sum('amount');

        $feeTypes = [
            MerchantWalletTransactionType::SHIPPING_FEE,
            MerchantWalletTransactionType::RETURN_FEE,
            MerchantWalletTransactionType::SWAP_FEE,
            MerchantWalletTransactionType::STORAGE_FEE,
            MerchantWalletTransactionType::PICK_FEE,
            MerchantWalletTransactionType::PACKING_FEE,
            MerchantWalletTransactionType::WAREHOUSE_CHARGE,
            MerchantWalletTransactionType::PENALTY,
        ];

        return [
            'available_balance' => number_format($approvedCredits - $approvedDebits, 2, '.', ''),
            'pending_credits' => number_format($pendingCredits, 2, '.', ''),
            'pending_debits' => number_format($pendingDebits, 2, '.', ''),
            'total_cod_collected' => number_format((float) (clone $approvedQuery)
                ->where('transaction_type', MerchantWalletTransactionType::COD_COLLECTED)
                ->sum('amount'), 2, '.', ''),
            'total_fees' => number_format((float) (clone $approvedQuery)
                ->whereIn('transaction_type', $feeTypes)
                ->sum('amount'), 2, '.', ''),
            'total_payouts' => number_format((float) (clone $approvedQuery)
                ->where('transaction_type', MerchantWalletTransactionType::PAYOUT)
                ->sum('amount'), 2, '.', ''),
            'total_manual_deposits' => number_format((float) (clone $approvedQuery)
                ->where('transaction_type', MerchantWalletTransactionType::MANUAL_DEPOSIT)
                ->sum('amount'), 2, '.', ''),
            'total_manual_withdraws' => number_format((float) (clone $approvedQuery)
                ->where('transaction_type', MerchantWalletTransactionType::MANUAL_WITHDRAW)
                ->sum('amount'), 2, '.', ''),
            'total_wallet_credits' => number_format($approvedCredits, 2, '.', ''),
            'total_wallet_debits' => number_format($approvedDebits, 2, '.', ''),
        ];
    }

    public function syncShipmentTransactions(Shipment $shipment, ?int $userId): void
    {
        $shipment->loadMissing(['order:id,order_type,payment_type']);

        if (! $this->shipmentSupportsLedger($shipment)) {
            return;
        }

        $deliveredCodAmount = (float) $shipment->delivered_cod_amount;

        if ($deliveredCodAmount > 0) {
            $this->createTransaction([
                'merchant_id' => $shipment->merchant_id,
                'transaction_type' => MerchantWalletTransactionType::COD_COLLECTED,
                'direction' => MerchantWalletDirection::CREDIT,
                'amount' => $deliveredCodAmount,
                'source_type' => 'shipment',
                'source_id' => $shipment->id,
                'status' => MerchantWalletTransactionStatus::APPROVED,
                'reason' => 'Shipment COD collected',
                'notes' => 'Auto-generated from shipment '.$shipment->shipment_number.'.',
                'created_by' => $userId,
                'approved_by' => $userId,
            ]);
        }

        $feeType = match ($shipment->order?->order_type) {
            OrderType::RETURN => MerchantWalletTransactionType::RETURN_FEE,
            OrderType::SWAP => MerchantWalletTransactionType::SWAP_FEE,
            default => MerchantWalletTransactionType::SHIPPING_FEE,
        };

        $shippingFee = (float) $shipment->shipping_fee;

        if ($shippingFee <= 0) {
            return;
        }

        $this->createTransaction([
            'merchant_id' => $shipment->merchant_id,
            'transaction_type' => $feeType,
            'direction' => MerchantWalletDirection::DEBIT,
            'amount' => $shippingFee,
            'source_type' => 'shipment',
            'source_id' => $shipment->id,
            'status' => MerchantWalletTransactionStatus::APPROVED,
            'reason' => 'Shipment service fee',
            'notes' => 'Auto-generated from shipment '.$shipment->shipment_number.'.',
            'created_by' => $userId,
            'approved_by' => $userId,
        ]);
    }

    public function syncWarehouseChargeTransaction(WarehouseCharge $warehouseCharge, ?int $userId): ?MerchantWalletTransaction
    {
        if ($warehouseCharge->status === WarehouseChargeStatus::CANCELLED) {
            return null;
        }

        $transactionType = match ($warehouseCharge->type) {
            WarehouseChargeType::STORAGE => MerchantWalletTransactionType::STORAGE_FEE,
            WarehouseChargeType::FULFILLMENT => MerchantWalletTransactionType::PICK_FEE,
            WarehouseChargeType::PACKAGING => MerchantWalletTransactionType::PACKING_FEE,
            default => MerchantWalletTransactionType::WAREHOUSE_CHARGE,
        };

        return $this->createTransaction([
            'merchant_id' => $warehouseCharge->merchant_id,
            'transaction_type' => $transactionType,
            'direction' => MerchantWalletDirection::DEBIT,
            'amount' => (float) $warehouseCharge->amount,
            'source_type' => 'warehouse_charge',
            'source_id' => $warehouseCharge->id,
            'status' => MerchantWalletTransactionStatus::APPROVED,
            'reason' => 'Warehouse charge',
            'notes' => $warehouseCharge->description,
            'created_by' => $userId,
            'approved_by' => $userId,
        ]);
    }

    public function syncPayoutTransaction(MerchantPayout $merchantPayout, ?int $userId): ?MerchantWalletTransaction
    {
        if ($merchantPayout->status !== \App\Modules\Finance\Enums\PayoutStatus::COMPLETED) {
            return null;
        }

        return $this->createTransaction([
            'merchant_id' => $merchantPayout->merchant_id,
            'transaction_type' => MerchantWalletTransactionType::PAYOUT,
            'direction' => MerchantWalletDirection::DEBIT,
            'amount' => (float) $merchantPayout->amount,
            'source_type' => 'merchant_payout',
            'source_id' => $merchantPayout->id,
            'status' => MerchantWalletTransactionStatus::APPROVED,
            'reason' => 'Merchant payout',
            'notes' => $merchantPayout->notes,
            'reference_number' => $merchantPayout->reference_number,
            'created_by' => $userId,
            'approved_by' => $merchantPayout->approved_by ?? $userId,
            'approved_at' => $merchantPayout->paid_at ?? now(),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<MerchantWalletTransaction>
     */
    public function ledgerQuery(int $merchantId, array $filters = []): Builder
    {
        return MerchantWalletTransaction::query()
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['transaction_type']), fn (Builder $query) => $query->where('transaction_type', $filters['transaction_type']))
            ->when(isset($filters['direction']), fn (Builder $query) => $query->where('direction', $filters['direction']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['source_type']), fn (Builder $query) => $query->where('source_type', $filters['source_type']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));
    }

    private function shipmentSupportsLedger(Shipment $shipment): bool
    {
        if (in_array($shipment->status, ShipmentStatus::financiallyCompletedValues(), true)) {
            return true;
        }

        return $shipment->order?->order_type === OrderType::RETURN
            && in_array($shipment->status, [
                ShipmentStatus::RETURNED,
                ShipmentStatus::RETURNED_TO_HUB,
                ShipmentStatus::RETURNED_TO_WAREHOUSE,
            ], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findExistingTransaction(array $data): ?MerchantWalletTransaction
    {
        if (! isset($data['source_type'], $data['source_id'], $data['transaction_type'])) {
            return null;
        }

        return MerchantWalletTransaction::withTrashed()
            ->where('merchant_id', $data['merchant_id'])
            ->where('source_type', $data['source_type'])
            ->where('source_id', $data['source_id'])
            ->where('transaction_type', $data['transaction_type'])
            ->first();
    }

    private function generateTransactionNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'MWT-'.$datePart.'-';

        $latest = MerchantWalletTransaction::withTrashed()
            ->where('transaction_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function nextApprovedBalance(int $merchantId, float $amount, string $direction): float
    {
        $latestApproved = MerchantWalletTransaction::query()
            ->where('merchant_id', $merchantId)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $currentBalance = $latestApproved !== null
            ? (float) $latestApproved->balance_after
            : 0.0;

        return $direction === MerchantWalletDirection::CREDIT
            ? $currentBalance + $amount
            : $currentBalance - $amount;
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'approvedBy',
            'rejectedBy',
            'createdBy',
        ];
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
