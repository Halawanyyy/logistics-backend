<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\Attachment\Models\Attachment;
use App\Modules\Attachment\Services\AttachmentService;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use App\Modules\Order\Models\Order;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class MerchantPortalAttachmentService
{
    /**
     * @var array<string, string>
     */
    private const CONTEXT_TO_ATTACHABLE_TYPE = [
        'merchant_profile' => 'merchants',
        'support_document' => 'merchants',
        'order' => 'orders',
        'shipment' => 'shipments',
        'invoice' => 'merchant_invoices',
        'payout' => 'merchant_payouts',
        'inbound_stock_request' => 'merchant_inbound_stock_requests',
        'integration' => 'merchant_integration_tokens',
    ];

    public function __construct(
        private readonly AttachmentService $attachmentService,
        private readonly MerchantPortalFinanceService $merchantPortalFinanceService,
        private readonly MerchantPortalInboundStockRequestService $merchantPortalInboundStockRequestService,
    ) {
    }

    public function queryForMerchant(int $merchantId): Builder
    {
        return Attachment::query()
            ->with('uploadedBy')
            ->where(function (Builder $query) use ($merchantId) {
                $query->where('merchant_id', $merchantId)
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'merchants')
                            ->where('attachable_id', $merchantId);
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'orders')
                            ->whereIn('attachable_id', Order::query()->where('merchant_id', $merchantId)->select('id'));
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'shipments')
                            ->whereIn('attachable_id', Shipment::query()->where('merchant_id', $merchantId)->select('id'));
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'merchant_invoices')
                            ->whereIn('attachable_id', MerchantInvoice::query()->where('merchant_id', $merchantId)->select('id'));
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'merchant_payouts')
                            ->whereIn('attachable_id', MerchantPayout::query()->where('merchant_id', $merchantId)->select('id'));
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'merchant_integration_tokens')
                            ->whereIn('attachable_id', MerchantIntegrationToken::query()->where('merchant_id', $merchantId)->select('id'));
                    })
                    ->orWhere(function (Builder $ownedQuery) use ($merchantId) {
                        $ownedQuery
                            ->where('attachable_type', 'merchant_inbound_stock_requests')
                            ->whereIn('attachable_id', MerchantInboundStockRequest::query()->where('merchant_id', $merchantId)->select('id'));
                    });
            })
            ->latest();
    }

    public function findOwned(int $merchantId, int $attachmentId): Attachment
    {
        return $this->queryForMerchant($merchantId)->whereKey($attachmentId)->firstOrFail();
    }

    public function contextFor(Attachment $attachment): string
    {
        return array_search($attachment->attachable_type, self::CONTEXT_TO_ATTACHABLE_TYPE, true) ?: 'support_document';
    }

    public function createForMerchant(int $merchantId, array $validated, UploadedFile $file, ?int $uploadedBy): Attachment
    {
        [$attachableType, $attachableId] = $this->resolveContextTarget($merchantId, $validated);

        $attachment = $this->attachmentService->create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'category' => $validated['context'],
            'notes' => $validated['notes'] ?? null,
            'merchant_id' => $merchantId,
            'merchant_uploaded' => true,
        ], $file, $uploadedBy);

        $attachment->setAttribute('context', $validated['context']);

        return $attachment;
    }

    public function deleteForMerchant(Attachment $attachment, ?int $deletedBy): void
    {
        if (! $attachment->merchant_uploaded) {
            $this->forbid('This attachment cannot be deleted from the merchant portal.');
        }

        $this->attachmentService->delete($attachment, $deletedBy);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{0: string, 1: int}
     */
    private function resolveContextTarget(int $merchantId, array $validated): array
    {
        $context = $validated['context'];
        $attachableType = self::CONTEXT_TO_ATTACHABLE_TYPE[$context];
        $resourceId = $this->requiredResourceId($validated, $context);

        return match ($context) {
            'merchant_profile', 'support_document' => [$attachableType, $merchantId],
            'order' => [$attachableType, Order::query()->where('merchant_id', $merchantId)->findOrFail($resourceId)->id],
            'shipment' => [$attachableType, Shipment::query()->where('merchant_id', $merchantId)->findOrFail($resourceId)->id],
            'invoice' => [$attachableType, $this->merchantPortalFinanceService->findOwnedInvoice($merchantId, $resourceId)->id],
            'payout' => [$attachableType, $this->merchantPortalFinanceService->findOwnedPayout($merchantId, $resourceId)->id],
            'inbound_stock_request' => [$attachableType, $this->merchantPortalInboundStockRequestService->findOwned($merchantId, $resourceId)->id],
            'integration' => [$attachableType, MerchantIntegrationToken::query()->where('merchant_id', $merchantId)->findOrFail($resourceId)->id],
            default => throw new HttpResponseException(response()->json(['message' => 'Unsupported attachment context.'], 422)),
        };
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function requiredResourceId(array $validated, string $context): int
    {
        if (in_array($context, ['merchant_profile', 'support_document'], true)) {
            return 0;
        }

        if (! isset($validated['resource_id'])) {
            throw new HttpResponseException(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'resource_id' => ['The resource_id field is required for this attachment context.'],
                ],
            ], 422));
        }

        return (int) $validated['resource_id'];
    }

    private function forbid(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 403));
    }
}
