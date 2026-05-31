<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Export\Services\ExportService;
use App\Modules\MerchantPortal\Requests\MerchantPortalScopedRequest;
use App\Modules\MerchantPortal\Requests\StoreMerchantPortalExportRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalExportResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MerchantPortalExportController
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalExportService $merchantPortalExportService,
    ) {
    }

    public function types(Request $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'exports.view');

        return response()->json([
            'data' => $this->merchantPortalExportService->supportedTypes(),
        ]);
    }

    public function store(StoreMerchantPortalExportRequest $request): JsonResponse
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'exports.create');

        $export = $this->merchantPortalExportService->createForMerchant(
            $request->validated(),
            $this->merchantPortalAccessService->merchantId($request),
            $request->user()?->id,
        );

        return (new MerchantPortalExportResource($export))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'exports.view');

        $exports = $this->merchantPortalExportService
            ->queryForMerchant($this->merchantPortalAccessService->merchantId($request))
            ->get();

        return MerchantPortalExportResource::collection($exports);
    }

    public function show(MerchantPortalScopedRequest $request, int $export): MerchantPortalExportResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'exports.view');

        return new MerchantPortalExportResource(
            $this->merchantPortalExportService->findOwned(
                $this->merchantPortalAccessService->merchantId($request),
                $export,
            )
        );
    }

    public function download(MerchantPortalScopedRequest $request, int $export)
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'exports.download');

        $exportLog = $this->merchantPortalExportService->findOwned(
            $this->merchantPortalAccessService->merchantId($request),
            $export,
        );

        abort_unless($exportLog->file_path !== null, 404, 'Export file not found.');

        $diskPath = $this->exportService->diskPath($exportLog->file_path);
        abort_unless(Storage::disk('exports')->exists($diskPath), 404, 'Export file not found.');

        return Response::download(
            Storage::disk('exports')->path($diskPath),
            basename($diskPath),
            ['Content-Type' => 'text/csv']
        );
    }
}
