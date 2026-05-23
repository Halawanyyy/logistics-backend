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
use App\Modules\Finance\Services\FinanceSummaryService;
use App\Modules\Finance\Services\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MerchantInvoiceController
{
    public function __construct(
        private readonly CreateMerchantInvoiceAction $createMerchantInvoiceAction,
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly InvoicePdfService $invoicePdfService,
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
            $this->financeSummaryService->getMerchantInvoicePreview($merchantInvoice->load(['merchant', 'items']))
        );
    }

    public function download(InvoiceDownloadRequest $request, MerchantInvoice $merchantInvoice)
    {
        $merchantInvoice->load(['merchant', 'items']);
        $path = $merchantInvoice->file_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            $preview = $this->financeSummaryService->getMerchantInvoicePreview($merchantInvoice);
            $pdf = $this->invoicePdfService->render($preview);
            $path = 'private/invoices/'.$merchantInvoice->invoice_number.'.pdf';

            Storage::disk('local')->put($path, $pdf);

            $merchantInvoice->update([
                'file_path' => $path,
                'generated_at' => now(),
            ]);
        }

        $merchantInvoice->increment('download_count');
        $merchantInvoice->update([
            'last_downloaded_at' => now(),
        ]);

        return Response::download(
            Storage::disk('local')->path($path),
            $merchantInvoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
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
