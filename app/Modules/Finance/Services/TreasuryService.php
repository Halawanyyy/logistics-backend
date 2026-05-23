<?php

namespace App\Modules\Finance\Services;
use App\Modules\Finance\Enums\DriverCashClosureStatus;
use App\Modules\Finance\Enums\ExpenseStatus;
use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Enums\TreasuryAccountStatus;
use App\Modules\Finance\Models\DriverCashClosure;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\TreasuryAccount;
use App\Modules\Finance\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function createTransaction(
        TreasuryAccount $treasuryAccount,
        string $direction,
        float $amount,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): TreasuryTransaction {
        return DB::transaction(function () use ($treasuryAccount, $direction, $amount, $sourceType, $sourceId, $notes, $createdBy) {
            if ($sourceType !== null && $sourceId !== null) {
                $existing = TreasuryTransaction::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['treasuryAccount', 'createdBy']);
                }
            }

            $treasuryAccount = TreasuryAccount::query()
                ->lockForUpdate()
                ->findOrFail($treasuryAccount->id);

            $currentBalance = (float) $treasuryAccount->current_balance;
            $balanceAfter = $direction === MerchantWalletDirection::CREDIT
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            $transaction = TreasuryTransaction::create([
                'treasury_account_id' => $treasuryAccount->id,
                'transaction_number' => $this->generateTransactionNumber(),
                'direction' => $direction,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_after' => number_format($balanceAfter, 2, '.', ''),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            $treasuryAccount->update([
                'current_balance' => number_format($balanceAfter, 2, '.', ''),
            ]);

            return $transaction->load(['treasuryAccount', 'createdBy']);
        });
    }

    public function syncDriverCashClosure(DriverCashClosure $driverCashClosure, ?int $userId): ?TreasuryTransaction
    {
        if ($driverCashClosure->status !== DriverCashClosureStatus::VERIFIED) {
            return null;
        }

        $account = $this->resolveAccount($driverCashClosure->treasury_account_id);

        if ($account === null) {
            return null;
        }

        if ($driverCashClosure->treasury_account_id === null) {
            $driverCashClosure->update(['treasury_account_id' => $account->id]);
        }

        return $this->createTransaction(
            $account,
            MerchantWalletDirection::CREDIT,
            (float) $driverCashClosure->received_amount,
            'driver_cash_closure',
            $driverCashClosure->id,
            $driverCashClosure->notes,
            $userId
        );
    }

    public function syncMerchantPayout(MerchantPayout $merchantPayout, ?int $userId): ?TreasuryTransaction
    {
        if ($merchantPayout->status !== PayoutStatus::COMPLETED) {
            return null;
        }

        $account = $this->resolveAccount($merchantPayout->treasury_account_id);

        if ($account === null) {
            return null;
        }

        if ($merchantPayout->treasury_account_id === null) {
            $merchantPayout->update(['treasury_account_id' => $account->id]);
        }

        return $this->createTransaction(
            $account,
            MerchantWalletDirection::DEBIT,
            (float) $merchantPayout->amount,
            'merchant_payout',
            $merchantPayout->id,
            $merchantPayout->notes,
            $userId
        );
    }

    public function syncExpense(Expense $expense, ?int $userId): ?TreasuryTransaction
    {
        if ($expense->status !== ExpenseStatus::PAID) {
            return null;
        }

        $account = $this->resolveAccount($expense->treasury_account_id);

        if ($account === null) {
            return null;
        }

        if ($expense->treasury_account_id === null) {
            $expense->update(['treasury_account_id' => $account->id]);
        }

        return $this->createTransaction(
            $account,
            MerchantWalletDirection::DEBIT,
            (float) $expense->amount,
            'expense',
            $expense->id,
            $expense->notes,
            $userId
        );
    }

    public function resolveAccount(?int $treasuryAccountId): ?TreasuryAccount
    {
        if ($treasuryAccountId !== null) {
            return TreasuryAccount::query()
                ->whereKey($treasuryAccountId)
                ->where('status', TreasuryAccountStatus::ACTIVE)
                ->first();
        }

        return TreasuryAccount::query()
            ->where('status', TreasuryAccountStatus::ACTIVE)
            ->orderBy('id')
            ->first();
    }

    private function generateTransactionNumber(): string
    {
        $datePart = now()->format('Ymd');
        $prefix = 'TRE-'.$datePart.'-';

        $latest = TreasuryTransaction::query()
            ->where('transaction_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
