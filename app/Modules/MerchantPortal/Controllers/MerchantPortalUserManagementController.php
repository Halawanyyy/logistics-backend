<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Models\Merchant;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalUsersRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalUserRequest;
use App\Modules\MerchantPortal\Requests\UpdateMerchantPortalUserRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalUserResource;
use App\Modules\MerchantPortal\Services\MerchantPortalUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalUserManagementController
{
    public function __construct(
        private readonly MerchantPortalUserManagementService $merchantPortalUserManagementService,
    ) {
    }

    public function index(ListMerchantPortalUsersRequest $request, Merchant $merchant): AnonymousResourceCollection
    {
        return MerchantPortalUserResource::collection(
            $this->merchantPortalUserManagementService->query($merchant, $request->validated())->get()
        );
    }

    public function store(StoreMerchantPortalUserRequest $request, Merchant $merchant): JsonResponse
    {
        return (new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->create($merchant, $request->validated())
        ))->response()->setStatusCode(201);
    }

    public function show(Merchant $merchant, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService
                ->ensureBelongsToMerchant($merchant, $merchantUser)
                ->load(['merchant', 'user', 'invitedBy'])
        );
    }

    public function update(UpdateMerchantPortalUserRequest $request, Merchant $merchant, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->update($merchant, $merchantUser, $request->validated())
        );
    }

    public function suspend(Merchant $merchant, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->suspend($merchant, $merchantUser)
        );
    }

    public function activate(Merchant $merchant, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->activate($merchant, $merchantUser)
        );
    }

    public function destroy(Merchant $merchant, MerchantUser $merchantUser): JsonResponse
    {
        $this->merchantPortalUserManagementService->delete($merchant, $merchantUser);

        return response()->json([
            'message' => 'Merchant portal user deleted successfully',
        ]);
    }
}
