<?php

namespace App\Modules\MerchantPortal\Controllers;

use App\Modules\Finance\Resources\MerchantInvoicePreviewResource;
use App\Modules\Finance\Services\MerchantInvoiceDocumentService;
use App\Modules\MerchantPortal\Requests\MerchantPortalFinanceScopedRequest;
use App\Modules\MerchantPortal\Requests\ListMerchantPortalInvoicesRequest;
use App\Modules\MerchantPortal\Resources\MerchantPortalInvoiceDetailResource;
use App\Modules\MerchantPortal\Resources\MerchantPortalInvoiceResource;
use App\Modules\MerchantPortal\Services\MerchantPortalAccessService;
use App\Modules\MerchantPortal\Services\MerchantPortalFinanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantPortalInvoiceController
{
    public function __construct(
        private readonly MerchantPortalAccessService $merchantPortalAccessService,
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
        private readonly MerchantInvoiceDocumentService $merchantInvoiceDocumentService,
    ) {
    }

    public function index(ListMerchantPortalInvoicesRequest $request): AnonymousResourceCollection
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'invoices.view');

        $invoices = $this->merchantPortalFinanceService
            ->invoiceQueryForMerchant($this->merchantPortalAccessService->merchantId($request), $request->validated())
            ->get()
            ->map(fn ($invoice) => $this->merchantPortalFinanceService->findOwnedInvoice(
                $this->merchantPortalAccessService->merchantId($request),
                $invoice->id,
            ));

        return MerchantPortalInvoiceResource::collection($invoices);
    }

    public function show(MerchantPortalFinanceScopedRequest $request, int $invoice): MerchantPortalInvoiceDetailResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'invoices.view');

        return new MerchantPortalInvoiceDetailResource(
            $this->merchantPortalFinanceService->findOwnedInvoice(
                $this->merchantPortalAccessService->merchantId($request),
                $invoice,
            )
        );
    }

    public function preview(MerchantPortalFinanceScopedRequest $request, int $invoice): MerchantInvoicePreviewResource
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $this->merchantPortalAccessService->assertCapability($merchantUser, 'invoices.view');

        $ownedInvoice = $this->merchantPortalFinanceService->findOwnedInvoice(
            $this->merchantPortalAccessService->merchantId($request),
            $invoice,
        );

        return new MerchantInvoicePreviewResource(
            $this->merchantInvoiceDocumentService->previewData($ownedInvoice)
        );
    }

    public function download(MerchantPortalFinanceScopedRequest $request, int $invoice)
    {
        $merchantUser = $this->merchantPortalAccessService->currentMerchantUser($request);
        $requiredCapability = $this->merchantPortalAccessService->can($merchantUser, 'invoices.download')
            ? 'invoices.download'
            : 'invoices.view';
        $this->merchantPortalAccessService->assertCapability($merchantUser, $requiredCapability);

        $ownedInvoice = $this->merchantPortalFinanceService->findOwnedInvoice(
            $this->merchantPortalAccessService->merchantId($request),
            $invoice,
        );

        return $this->merchantInvoiceDocumentService->downloadResponse($ownedInvoice);
    }
}
