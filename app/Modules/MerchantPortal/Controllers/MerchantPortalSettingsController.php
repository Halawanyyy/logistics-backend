<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\UpdateMerchantPortalSettingsRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalSettingsResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalSettingsService;
use Illuminate\Http\Request;

class MerchantPortalSettingsController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalSettingsService $merchantPortalSettingsService,
    ) {
    }

    public function show(Request $request): MerchantPortalSettingsResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'profile.view');

        return new MerchantPortalSettingsResource(
            $this->merchantPortalSettingsService->getForMerchant(
                $this->merchantPortalAccessService->currentMerchant($request)
            )
        );
    }

    public function update(UpdateMerchantPortalSettingsRequest $request): MerchantPortalSettingsResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'profile.update');

        return new MerchantPortalSettingsResource(
            $this->merchantPortalSettingsService->updateForMerchant(
                $this->merchantPortalAccessService->currentMerchant($request),
                $request->validated(),
            )
        );
    }
}
