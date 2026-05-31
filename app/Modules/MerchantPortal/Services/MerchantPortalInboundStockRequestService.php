<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\MerchantPortal\Enums\MerchantInboundStockRequestStatus;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use App\Modules\Warehouse\Enums\WarehouseReceiptStatus;
use App\Modules\Warehouse\Models\WarehouseReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class MerchantPortalInboundStockRequestService
{
    /**
     * @param array<string, mixed> $filters
     */
    public function queryForMerchant(int $merchantId, array $filters = []): Builder
    {
        return MerchantInboundStockRequest::query()
            ->with($this->relations())
            ->where('merchant_id', $merchantId)
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['request_number']), fn ($query) => $query->where('request_number', 'like', '%'.$filters['request_number'].'%'))
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['expected_arrival_from']), fn ($query) => $query->whereDate('expected_arrival_date', '>=', $filters['expected_arrival_from']))
            ->when(isset($filters['expected_arrival_to']), fn ($query) => $query->whereDate('expected_arrival_date', '<=', $filters['expected_arrival_to']))
            ->latest();
    }

    public function findOwned(int $merchantId, int $requestId): MerchantInboundStockRequest
    {
        return $this->queryForMerchant($merchantId)
            ->whereKey($requestId)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function createForMerchant(array $validated, int $merchantId, int $requestedBy): MerchantInboundStockRequest
    {
        return DB::transaction(function () use ($validated, $merchantId, $requestedBy) {
            $request = MerchantInboundStockRequest::create([
                'request_number' => $this->generateRequestNumber(),
                'merchant_id' => $merchantId,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'status' => MerchantInboundStockRequestStatus::SUBMITTED,
                'requested_by' => $requestedBy,
                'expected_arrival_date' => $validated['expected_arrival_date'] ?? null,
                'submitted_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $request->items()->createMany(array_map(static fn (array $item): array => [
                'warehouse_product_id' => $item['warehouse_product_id'] ?? null,
                'requested_product_name' => $item['requested_product_name'] ?? null,
                'requested_sku' => $item['requested_sku'] ?? null,
                'quantity' => $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ], $validated['items']));

            return $request->fresh()->load($this->relations());
        });
    }

    public function cancelForMerchant(MerchantInboundStockRequest $inboundRequest): MerchantInboundStockRequest
    {
        return DB::transaction(function () use ($inboundRequest) {
            $request = MerchantInboundStockRequest::query()
                ->lockForUpdate()
                ->findOrFail($inboundRequest->id);

            if (! in_array((string) $request->status, MerchantInboundStockRequestStatus::cancellableValues(), true)) {
                $this->fail('Inbound stock request cannot be cancelled in its current status.');
            }

            $request->update([
                'status' => MerchantInboundStockRequestStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $request->fresh()->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function adminQuery(array $filters = []): Builder
    {
        return MerchantInboundStockRequest::query()
            ->with($this->relations())
            ->when(isset($filters['merchant_id']), fn ($query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['warehouse_id']), fn ($query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['request_number']), fn ($query) => $query->where('request_number', 'like', '%'.$filters['request_number'].'%'))
            ->latest();
    }

    public function findAdmin(int $requestId): MerchantInboundStockRequest
    {
        return MerchantInboundStockRequest::query()
            ->with($this->relations())
            ->whereKey($requestId)
            ->firstOrFail();
    }

    public function approve(MerchantInboundStockRequest $inboundRequest, ?int $userId, ?string $internalNotes = null): MerchantInboundStockRequest
    {
        return DB::transaction(function () use ($inboundRequest, $userId, $internalNotes) {
            $request = MerchantInboundStockRequest::query()->lockForUpdate()->findOrFail($inboundRequest->id);

            if (! in_array((string) $request->status, [
                MerchantInboundStockRequestStatus::SUBMITTED,
                MerchantInboundStockRequestStatus::DRAFT,
            ], true)) {
                $this->fail('Only submitted or draft inbound requests can be approved.');
            }

            $request->update([
                'status' => MerchantInboundStockRequestStatus::APPROVED,
                'reviewed_by' => $userId,
                'internal_notes' => $internalNotes ?? $request->internal_notes,
            ]);

            return $request->fresh()->load($this->relations());
        });
    }

    public function reject(MerchantInboundStockRequest $inboundRequest, ?int $userId, ?string $internalNotes = null): MerchantInboundStockRequest
    {
        return DB::transaction(function () use ($inboundRequest, $userId, $internalNotes) {
            $request = MerchantInboundStockRequest::query()->lockForUpdate()->findOrFail($inboundRequest->id);

            if (in_array((string) $request->status, [
                MerchantInboundStockRequestStatus::REJECTED,
                MerchantInboundStockRequestStatus::RECEIVED,
                MerchantInboundStockRequestStatus::PARTIALLY_RECEIVED,
                MerchantInboundStockRequestStatus::CLOSED,
            ], true)) {
                $this->fail('Inbound request cannot be rejected in its current status.');
            }

            $request->update([
                'status' => MerchantInboundStockRequestStatus::REJECTED,
                'reviewed_by' => $userId,
                'closed_at' => now(),
                'internal_notes' => $internalNotes ?? $request->internal_notes,
            ]);

            return $request->fresh()->load($this->relations());
        });
    }

    public function linkWarehouseReceipt(MerchantInboundStockRequest $inboundRequest, WarehouseReceipt $warehouseReceipt, ?int $userId, ?string $internalNotes = null): MerchantInboundStockRequest
    {
        return DB::transaction(function () use ($inboundRequest, $warehouseReceipt, $userId, $internalNotes) {
            $request = MerchantInboundStockRequest::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($inboundRequest->id);

            $receipt = WarehouseReceipt::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($warehouseReceipt->id);

            if ((int) $request->merchant_id !== (int) $receipt->merchant_id) {
                $this->fail('Warehouse receipt must belong to the same merchant as the inbound request.');
            }

            $requestedQuantity = (int) $request->items->sum('quantity');
            $acceptedQuantity = (int) $receipt->items->sum('accepted_quantity');

            $status = $request->status;
            $receivedAt = $request->received_at;

            if ($receipt->status === WarehouseReceiptStatus::CONFIRMED) {
                $status = $acceptedQuantity >= $requestedQuantity
                    ? MerchantInboundStockRequestStatus::RECEIVED
                    : MerchantInboundStockRequestStatus::PARTIALLY_RECEIVED;
                $receivedAt = now();
            }

            $request->update([
                'linked_warehouse_receipt_id' => $receipt->id,
                'reviewed_by' => $userId,
                'status' => $status,
                'received_at' => $receivedAt,
                'internal_notes' => $internalNotes ?? $request->internal_notes,
            ]);

            return $request->fresh()->load($this->relations());
        });
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'merchant',
            'warehouse',
            'requestedBy',
            'reviewedBy',
            'linkedWarehouseReceipt',
            'items.warehouseProduct',
        ];
    }

    private function generateRequestNumber(): string
    {
        $prefix = 'MIR-'.now()->format('Ymd').'-';

        $latest = MerchantInboundStockRequest::query()
            ->lockForUpdate()
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $nextSequence = $latest === null ? 1 : ((int) substr((string) $latest, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    private function fail(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
