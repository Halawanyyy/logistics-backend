<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\MerchantPortalScopedRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalIntegrationTokenRequest;
use App\Modules\MerchantPortal\Requests\TestMerchantPortalIntegrationRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalIntegrationTokenResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalIntegrationTokenController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalIntegrationService $merchantPortalIntegrationService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.view');

        $tokens = $this->merchantPortalIntegrationService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request))
            ->get();

        return MerchantPortalIntegrationTokenResource::collection($tokens);
    }

    public function store(StoreMerchantPortalIntegrationTokenRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.manage');

        $result = $this->merchantPortalIntegrationService->createForMerchant(
            $this->merchantPortalAccessService->currentMerchant($request),
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'data' => array_merge(
                (new MerchantPortalIntegrationTokenResource($result['model']))->resolve(),
                ['plain_token' => $result['plain_token']]
            ),
        ], 201);
    }

    public function show(MerchantPortalScopedRequest $request, int $token): MerchantPortalIntegrationTokenResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.view');

        return new MerchantPortalIntegrationTokenResource(
            $this->merchantPortalIntegrationService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $token,
            )
        );
    }

    public function revoke(MerchantPortalScopedRequest $request, int $token): MerchantPortalIntegrationTokenResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.manage');

        return new MerchantPortalIntegrationTokenResource(
            $this->merchantPortalIntegrationService->revoke(
                $this->merchantPortalIntegrationService->findOwned(
                    $this->merchantPortalAccessService->merchantId($request),
                    $token,
                ),
                $request->user(),
            )
        );
    }

    public function destroy(MerchantPortalScopedRequest $request, int $token): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.manage');

        $this->merchantPortalIntegrationService->destroy(
            $this->merchantPortalIntegrationService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $token,
            ),
            $request->user(),
        );

        return response()->json([
            'message' => 'Integration token deleted successfully',
        ]);
    }

    public function docs(Request $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.view');

        return response()->json([
            'data' => $this->merchantPortalIntegrationService->docs(),
        ]);
    }

    public function test(TestMerchantPortalIntegrationRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'integrations.view');

        return response()->json([
            'data' => $this->merchantPortalIntegrationService->testPayload($request->validated()),
        ]);
    }
}
