<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Merchant\Enums\MerchantStatus;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Resources\MerchantPortalMeResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalCapabilityService;
use Illuminate\Http\Request;

class MerchantPortalMeController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalCapabilityService $merchantPortalCapabilityService,
    ) {}

    public function show(Request $request): MerchantPortalMeResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        $merchantUser->forceFill(['last_login_at' => now()])->saveQuietly();

        return new MerchantPortalMeResource([
            'user' => $request->user(),
            'merchant' => $merchant,
            'merchant_user' => $merchantUser->fresh(),
            'capabilities' => $merchant->status === MerchantStatus::ACTIVE
                && $merchantUser->status === MerchantPortalUserStatus::ACTIVE
                ? $this->merchantPortalCapabilityService->forRole($merchantUser->role)
                : ['profile.view', 'profile.update', 'settings.view', 'settings.update', 'notifications.view'],
        ]);
    }
}
