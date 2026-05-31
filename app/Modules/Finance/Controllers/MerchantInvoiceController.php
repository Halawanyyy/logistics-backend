<?php

namespace App\Modules\Finance\Controllers;

use App\Models\Merchant;
use App\Modules\Finance\Actions\CreateMerchantInvoiceAction;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Requests\InvoiceDownloadRequest;
use App\Modules\Finance\Requests\InvoicePreviewRequest;
use App\Modules\Finance\Requests\ListMerchantInvoicesRequest;
use App\Modules\Finance\Requests\StoreMerchantInvoiceRequest;
use App\Modules\Finance\Requests\UpdateMerchantInvoiceRequest;
use App\Modules\Finance\Resources\MerchantInvoicePreviewResource;
use App\Modules\Finance\Resources\MerchantInvoiceResource;
use App\Modules\Finance\Services\MerchantInvoiceDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantInvoiceController
{
    public function __construct(
        private readonly CreateMerchantInvoiceAction $createMerchantInvoiceAction,
        private readonly MerchantInvoiceDocumentService $merchantInvoiceDocumentService,
    ) {
    }

    public function index(ListMerchantInvoicesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $invoices = MerchantInvoice::query()
            ->with(['merchant', 'createdBy'])
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->latest()
            ->get();

        return MerchantInvoiceResource::collection($invoices);
    }

    public function store(StoreMerchantInvoiceRequest $request): JsonResponse
    {
        $merchant = Merchant::query()->findOrFail($request->integer('merchant_id'));
        $invoice = $this->createMerchantInvoiceAction->execute($merchant, $request->validated(), $request->user()?->id);

        return (new MerchantInvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MerchantInvoice $merchantInvoice): MerchantInvoiceResource
    {
        return new MerchantInvoiceResource($merchantInvoice->load(['merchant', 'createdBy', 'items']));
    }

    public function preview(InvoicePreviewRequest $request, MerchantInvoice $merchantInvoice): MerchantInvoicePreviewResource
    {
        return new MerchantInvoicePreviewResource(
            $this->merchantInvoiceDocumentService->previewData($merchantInvoice)
        );
    }

    public function download(InvoiceDownloadRequest $request, MerchantInvoice $merchantInvoice)
    {
        return $this->merchantInvoiceDocumentService->downloadResponse($merchantInvoice);
    }

    public function update(UpdateMerchantInvoiceRequest $request, MerchantInvoice $merchantInvoice): MerchantInvoiceResource
    {
        $merchant = $merchantInvoice->merchant()->firstOrFail();
        $invoice = $this->createMerchantInvoiceAction->refresh(
            $merchantInvoice,
            $merchant,
            $request->validated()
        );

        return new MerchantInvoiceResource($invoice);
    }

    public function destroy(MerchantInvoice $merchantInvoice): JsonResponse
    {
        $merchantInvoice->delete();

        return response()->json([
            'message' => 'Merchant invoice deleted successfully',
        ]);
    }
}
