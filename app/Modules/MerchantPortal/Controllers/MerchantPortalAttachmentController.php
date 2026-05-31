<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\MerchantPortal\Requests\MerchantPortalScopedRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalAttachmentRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalAttachmentResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MerchantPortalAttachmentController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalAttachmentService $merchantPortalAttachmentService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'attachments.view');

        $attachments = $this->merchantPortalAttachmentService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request))
            ->get()
            ->each(fn ($attachment) => $attachment->setAttribute('context', $this->merchantPortalAttachmentService->contextFor($attachment)));

        return MerchantPortalAttachmentResource::collection($attachments);
    }

    public function store(StoreMerchantPortalAttachmentRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'attachments.upload');

        $attachment = $this->merchantPortalAttachmentService->createForMerchant(
            $this->merchantPortalAccessService->merchantId($request),
            $request->validated(),
            $request->file('file'),
            $request->user()?->id,
        );

        return (new MerchantPortalAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MerchantPortalScopedRequest $request, int $attachment): MerchantPortalAttachmentResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'attachments.view');

        $ownedAttachment = $this->merchantPortalAttachmentService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $attachment,
        );
        $ownedAttachment->setAttribute('context', $this->merchantPortalAttachmentService->contextFor($ownedAttachment));

        return new MerchantPortalAttachmentResource($ownedAttachment);
    }

    public function download(MerchantPortalScopedRequest $request, int $attachment)
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'attachments.download');

        $ownedAttachment = $this->merchantPortalAttachmentService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $attachment,
        );
        $diskPath = str_starts_with($ownedAttachment->file_path, 'attachments/')
            ? substr($ownedAttachment->file_path, strlen('attachments/'))
            : $ownedAttachment->file_path;

        abort_unless(Storage::disk('attachments')->exists($diskPath), 404, 'Attachment file not found.');

        return Response::download(
            Storage::disk('attachments')->path($diskPath),
            $ownedAttachment->original_name,
            ['Content-Type' => $ownedAttachment->mime_type ?? 'application/octet-stream']
        );
    }

    public function destroy(MerchantPortalScopedRequest $request, int $attachment): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'attachments.delete');

        $this->merchantPortalAttachmentService->deleteForMerchant(
            $this->merchantPortalAttachmentService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $attachment,
            ),
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Attachment deleted successfully',
        ]);
    }
}
