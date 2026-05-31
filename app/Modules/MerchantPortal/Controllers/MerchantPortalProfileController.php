<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\UpdateMerchantPortalProfileRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalProfileResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use Illuminate\Http\Request;

class MerchantPortalProfileController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
    ) {
    }

    public function show(Request $request): MerchantPortalProfileResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'profile.view');

        return new MerchantPortalProfileResource(
            $this->merchantPortalAccessService->currentMerchant($request)
        );
    }

    public function update(UpdateMerchantPortalProfileRequest $request): MerchantPortalProfileResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'profile.update');

        $merchant = $this->merchantPortalAccessService->currentMerchant($request);
        $merchant->update($request->validated());

        return new MerchantPortalProfileResource($merchant->fresh());
    }
}
