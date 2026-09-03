<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalUsersRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalUserRequest;
use App\Modules\MerchantPortal\Requests\UpdateMerchantPortalUserRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalUserResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalUserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalUsersController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalUserManagementService $merchantPortalUserManagementService,
    ) {
    }

    public function index(ListMerchantPortalUsersRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'users.view');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        return MerchantPortalUserResource::collection(
            $this->merchantPortalUserManagementService->query($merchant, $request->validated())->get()
        );
    }

    public function store(StoreMerchantPortalUserRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'users.manage');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        $data = $request->validated();
        // Set the invited_by to the current user
        $data['invited_by'] = $merchantUser->user_id;

        return (new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->create($merchant, $data)
        ))->response()->setStatusCode(201);
    }

    public function show(ListMerchantPortalUsersRequest $request, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        $currentMerchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($currentMerchantUser, 'users.view');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService
                ->ensureBelongsToMerchant($merchant, $merchantUser)
                ->load(['merchant', 'user', 'invitedBy'])
        );
    }

    public function update(UpdateMerchantPortalUserRequest $request, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        $currentMerchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($currentMerchantUser, 'users.manage');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->update($merchant, $merchantUser, $request->validated())
        );
    }

    public function suspend(ListMerchantPortalUsersRequest $request, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        $currentMerchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($currentMerchantUser, 'users.manage');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->suspend($merchant, $merchantUser)
        );
    }

    public function activate(ListMerchantPortalUsersRequest $request, MerchantUser $merchantUser): MerchantPortalUserResource
    {
        $currentMerchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($currentMerchantUser, 'users.manage');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        return new MerchantPortalUserResource(
            $this->merchantPortalUserManagementService->activate($merchant, $merchantUser)
        );
    }

    public function destroy(ListMerchantPortalUsersRequest $request, MerchantUser $merchantUser): JsonResponse
    {
        $currentMerchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($currentMerchantUser, 'users.manage');
        $merchant = $this->merchantPortalAccessService->currentMerchant($request);

        $this->merchantPortalUserManagementService->delete($merchant, $merchantUser);

        return response()->json([
            'message' => 'Merchant portal user deleted successfully',
        ]);
    }
}
