<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalWalletTransactionsRequest;
use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceScopedRequest;
use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceSummaryRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalWalletSummaryResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalWalletTransactionResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalFinanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalWalletController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
    ) {
    }

    public function summary(MerchantPortalFinanceSummaryRequest $request): MerchantPortalWalletSummaryResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'finance.view');

        return new MerchantPortalWalletSummaryResource(
            $this->merchantPortalFinanceService->walletSummary(
                $this->merchantPortalAccessService->currentMerchant($request),
                $request->validated(),
            )
        );
    }

    public function transactions(ListMerchantPortalWalletTransactionsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'finance.view');

        $transactions = $this->merchantPortalFinanceService
            ->walletTransactionQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalWalletTransactionResource::collection($transactions);
    }

    public function show(MerchantPortalFinanceScopedRequest $request, int $transaction): MerchantPortalWalletTransactionResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'finance.view');

        return new MerchantPortalWalletTransactionResource(
            $this->merchantPortalFinanceService->findOwnedWalletTransaction(
                $this->merchantPortalAccessService->merchantId($request),
                $transaction,
            )
        );
    }
}
