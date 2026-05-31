<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceScopedRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalPayoutsRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalPayoutRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalPayoutDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalPayoutResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalPayoutController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
    ) {
    }

    public function index(ListMerchantPortalPayoutsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $requiredCapability = $this->merchantPortalAccessService->can($merchantUser, 'payouts.view')
            ? 'payouts.view'
            : 'finance.view';
        $this->merchantPortalAccessService->assertCapability($merchantUser, $requiredCapability);

        $payouts = $this->merchantPortalFinanceService
            ->payoutQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get();

        return MerchantPortalPayoutResource::collection($payouts);
    }

    public function show(MerchantPortalFinanceScopedRequest $request, int $payout): MerchantPortalPayoutDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $requiredCapability = $this->merchantPortalAccessService->can($merchantUser, 'payouts.view')
            ? 'payouts.view'
            : 'finance.view';
        $this->merchantPortalAccessService->assertCapability($merchantUser, $requiredCapability);

        return new MerchantPortalPayoutDetailResource(
            $this->merchantPortalFinanceService->findOwnedPayout(
                $this->merchantPortalAccessService->merchantId($request),
                $payout,
            )
        );
    }

    public function requestPayout(StoreMerchantPortalPayoutRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'payouts.request');

        $payout = $this->merchantPortalFinanceService->requestPayout(
            $this->merchantPortalAccessService->currentMerchant($request),
            $request->validated(),
            $request->user()?->id,
        );

        return (new MerchantPortalPayoutDetailResource($payout))
            ->response()
            ->setStatusCode(201);
    }
}
