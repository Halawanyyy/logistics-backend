<?php

namespace App\Modules\Finance\Controllers;

use App\Models\Merchant;
use App\Modules\Finance\Enums\MerchantWalletDirection;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionType;
use App\Modules\Finance\Resources\MerchantWalletTransactionResource;
use App\Modules\Finance\Services\MerchantWalletService;
use App\Modules\Settings\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MerchantWalletController
{
    public function __construct(
        private readonly MerchantWalletService $merchantWalletService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function summary(Request $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return response()->json([
            'data' => [
                'merchant_id' => $merchant->id,
                'merchant_name' => $merchant->name,
                ...$this->merchantWalletService->getLedgerSummary($merchant->id, $validated),
            ],
        ]);
    }

    public function transactions(Request $request, Merchant $merchant): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'transaction_type' => ['nullable', Rule::in(MerchantWalletTransactionType::values())],
            'direction' => ['nullable', Rule::in(MerchantWalletDirection::values())],
            'status' => ['nullable', Rule::in(MerchantWalletTransactionStatus::values())],
            'source_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $transactions = $this->merchantWalletService
            ->ledgerQuery($merchant->id, $validated)
            ->with(['merchant', 'approvedBy', 'rejectedBy', 'createdBy'])
            ->latest()
            ->get();

        return MerchantWalletTransactionResource::collection($transactions);
    }

    public function deposit(Request $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $requiresApproval = (bool) $this->systemSettingService->get('wallet_manual_transactions_require_approval', true);
        $autoApprove = $user?->isSuperAdmin() === true || ! $requiresApproval;

        $transaction = $this->merchantWalletService->createTransaction([
            'merchant_id' => $merchant->id,
            'transaction_type' => MerchantWalletTransactionType::MANUAL_DEPOSIT,
            'direction' => MerchantWalletDirection::CREDIT,
            'amount' => $validated['amount'],
            'status' => $autoApprove ? MerchantWalletTransactionStatus::APPROVED : MerchantWalletTransactionStatus::PENDING,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'created_by' => $user?->id,
            'approved_by' => $autoApprove ? $user?->id : null,
        ]);

        return (new MerchantWalletTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }

    public function withdraw(Request $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $requiresApproval = (bool) $this->systemSettingService->get('wallet_manual_transactions_require_approval', true);
        $autoApprove = $user?->isSuperAdmin() === true || ! $requiresApproval;

        $transaction = $this->merchantWalletService->createTransaction([
            'merchant_id' => $merchant->id,
            'transaction_type' => MerchantWalletTransactionType::MANUAL_WITHDRAW,
            'direction' => MerchantWalletDirection::DEBIT,
            'amount' => $validated['amount'],
            'status' => $autoApprove ? MerchantWalletTransactionStatus::APPROVED : MerchantWalletTransactionStatus::PENDING,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'created_by' => $user?->id,
            'approved_by' => $autoApprove ? $user?->id : null,
        ]);

        return (new MerchantWalletTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }
}
