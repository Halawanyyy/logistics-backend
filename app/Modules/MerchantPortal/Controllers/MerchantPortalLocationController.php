<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\LocationPricing\Models\Area;
use App\Modules\LocationPricing\Models\Governorate;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalAreasRequest;
use App\Modules\MerchantPortal\Requests\MerchantPortalScopedRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalLocationOptionResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalLocationController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
    ) {
    }

    public function governorates(MerchantPortalScopedRequest $request): AnonymousResourceCollection
    {
        $this->merchantPortalAccessService->currentMerchantUser($request);

        $governorates = Governorate::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return MerchantPortalLocationOptionResource::collection($governorates);
    }

    public function areas(ListMerchantPortalAreasRequest $request): AnonymousResourceCollection
    {
        $this->merchantPortalAccessService->currentMerchantUser($request);

        $areas = Area::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('governorate_id', $request->integer('governorate_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return MerchantPortalLocationOptionResource::collection($areas);
    }
}
