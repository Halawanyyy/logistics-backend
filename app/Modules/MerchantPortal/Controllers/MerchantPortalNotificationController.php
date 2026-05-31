<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\ListMerchantPortalNotificationsRequest;
use App\Modules\MerchantPortal\Requests\MerchantPortalScopedRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalNotificationResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalNotificationController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalNotificationService $merchantPortalNotificationService,
    ) {
    }

    public function index(ListMerchantPortalNotificationsRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'notifications.view');

        return MerchantPortalNotificationResource::collection(
            $this->merchantPortalNotificationService
                ->queryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
                ->get()
        );
    }

    public function unreadCount(MerchantPortalScopedRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'notifications.view');

        return response()->json([
            'data' => [
                'unread_count' => $this->merchantPortalNotificationService->unreadCount(
                    $this->merchantPortalAccessService->merchantId($request)
                ),
            ],
        ]);
    }

    public function markRead(MerchantPortalScopedRequest $request, int $notification): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'notifications.view');

        $updatedNotification = $this->merchantPortalNotificationService->markRead(
            $this->merchantPortalNotificationService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $notification,
            )
        );

        return response()->json([
            'data' => new MerchantPortalNotificationResource($updatedNotification)
        ]);
    }

    public function markAllRead(MerchantPortalScopedRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'notifications.view');

        return MerchantPortalNotificationResource::collection(
            $this->merchantPortalNotificationService->markAllRead(
                $this->merchantPortalAccessService->merchantId($request)
            )
        );
    }
}
