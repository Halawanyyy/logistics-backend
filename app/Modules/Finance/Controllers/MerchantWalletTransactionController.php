<?php

namespace App\Modules\Finance\Controllers;

use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Resources\MerchantWalletTransactionResource;
use App\Modules\Finance\Services\MerchantWalletService;
use Illuminate\Http\Request;

class MerchantWalletTransactionController
{
    public function __construct(
        private readonly MerchantWalletService $merchantWalletService,
    ) {
    }

    public function show(MerchantWalletTransaction $transaction): MerchantWalletTransactionResource
    {
        return new MerchantWalletTransactionResource($transaction->load([
            'merchant',
            'approvedBy',
            'rejectedBy',
            'createdBy',
        ]));
    }

    public function approve(Request $request, MerchantWalletTransaction $transaction): MerchantWalletTransactionResource
    {
        return new MerchantWalletTransactionResource(
            $this->merchantWalletService->approveTransaction($transaction, $request->user()?->id)
        );
    }

    public function reject(Request $request, MerchantWalletTransaction $transaction): MerchantWalletTransactionResource
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        return new MerchantWalletTransactionResource(
            $this->merchantWalletService->rejectTransaction(
                $transaction,
                $validated['rejection_reason'],
                $request->user()?->id
            )
        );
    }
}
