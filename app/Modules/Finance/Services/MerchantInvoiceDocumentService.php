<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\MerchantInvoice;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class MerchantInvoiceDocumentService
{
    public function __construct(
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly InvoicePdfService $invoicePdfService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function previewData(MerchantInvoice $merchantInvoice): array
    {
        return $this->financeSummaryService->getMerchantInvoicePreview(
            $merchantInvoice->load(['merchant', 'items'])
        );
    }

    public function downloadResponse(MerchantInvoice $merchantInvoice)
    {
        $persistentInvoice = MerchantInvoice::query()->findOrFail($merchantInvoice->getKey());
        $persistentInvoice->load(['merchant', 'items']);
        $path = $persistentInvoice->file_path !== null
            ? $this->safeInvoicePath($persistentInvoice->file_path)
            : null;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            $preview = $this->financeSummaryService->getMerchantInvoicePreview($persistentInvoice);
            $pdf = $this->invoicePdfService->render($preview);
            $path = 'private/invoices/'.$persistentInvoice->invoice_number.'.pdf';

            Storage::disk('local')->put($path, $pdf);

            $persistentInvoice->update([
                'file_path' => $path,
                'generated_at' => now(),
            ]);
        }

        $persistentInvoice->increment('download_count');
        $persistentInvoice->update([
            'last_downloaded_at' => now(),
        ]);

        return Response::download(
            Storage::disk('local')->path($path),
            $persistentInvoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function safeInvoicePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (
            ! str_starts_with($normalized, 'private/invoices/')
            || str_contains($normalized, '../')
            || str_contains($normalized, '..\\')
        ) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invoice file not found.',
            ], 404));
        }

        return $normalized;
    }
}
