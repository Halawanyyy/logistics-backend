<?php

namespace App\Modules\MerchantPortal\Services;

use App\Modules\Export\Models\ExportLog;
use App\Modules\Export\Services\ExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;

class MerchantPortalExportService
{
    /**
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'orders' => 'orders',
        'shipments' => 'shipments',
        'warehouse_inventory' => 'warehouse_inventory',
        'stock_movements' => 'stock_movements',
        'wallet_transactions' => 'wallet_transactions',
        'invoices' => 'invoices',
        'payouts' => 'payouts',
        'inbound_stock_requests' => 'inbound_stock_requests',
    ];

    public function __construct(
        private readonly ExportService $exportService,
    ) {
    }

    /**
     * @return list<array<string, string>>
     */
    public function supportedTypes(): array
    {
        return [
            ['type' => 'orders', 'label' => 'Orders'],
            ['type' => 'shipments', 'label' => 'Shipments'],
            ['type' => 'warehouse_inventory', 'label' => 'Warehouse Inventory'],
            ['type' => 'stock_movements', 'label' => 'Stock Movements'],
            ['type' => 'wallet_transactions', 'label' => 'Wallet Transactions'],
            ['type' => 'invoices', 'label' => 'Invoices'],
            ['type' => 'payouts', 'label' => 'Payouts'],
            ['type' => 'inbound_stock_requests', 'label' => 'Inbound Stock Requests'],
        ];
    }

    public function queryForMerchant(int $merchantId): Builder
    {
        return ExportLog::query()
            ->where('merchant_id', $merchantId)
            ->latest('generated_at');
    }

    public function findOwned(int $merchantId, int $exportId): ExportLog
    {
        return $this->queryForMerchant($merchantId)->whereKey($exportId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function createForMerchant(array $validated, int $merchantId, ?int $generatedBy): ExportLog
    {
        $type = $validated['type'];
        $filters = $validated['filters'] ?? [];

        $this->validateFilters($type, $filters);

        return $this->exportService->generate(
            self::TYPE_MAP[$type],
            'csv',
            [
                ...$filters,
                'merchant_id' => $merchantId,
            ],
            $generatedBy,
            $merchantId,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function validateFilters(string $type, array $filters): void
    {
        if ($this->containsMerchantId($filters)) {
            $this->fail('filters.merchant_id', 'merchant_id cannot be submitted for merchant portal exports.');
        }

        $rules = [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:100'],
        ];

        $typeSpecific = match ($type) {
            'orders' => [
                'order_number' => ['nullable', 'string', 'max:255'],
            ],
            'shipments' => [
                'shipment_number' => ['nullable', 'string', 'max:255'],
            ],
            'warehouse_inventory' => [
                'warehouse_id' => ['nullable', 'integer', 'min:1'],
            ],
            'stock_movements' => [
                'warehouse_id' => ['nullable', 'integer', 'min:1'],
                'movement_type' => ['nullable', 'string', 'max:100'],
            ],
            'wallet_transactions' => [
                'transaction_type' => ['nullable', 'string', 'max:100'],
            ],
            'invoices' => [
                'invoice_number' => ['nullable', 'string', 'max:255'],
            ],
            'payouts' => [
                'payout_number' => ['nullable', 'string', 'max:255'],
            ],
            'inbound_stock_requests' => [
                'request_number' => ['nullable', 'string', 'max:255'],
                'warehouse_id' => ['nullable', 'integer', 'min:1'],
            ],
            default => [],
        };

        $validator = Validator::make($filters, array_merge($rules, $typeSpecific));

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()->toArray(),
            ], 422));
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function containsMerchantId(array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key === 'merchant_id') {
                return true;
            }

            if (is_array($value) && $this->containsMerchantId($value)) {
                return true;
            }
        }

        return false;
    }

    private function fail(string $field, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                $field => [$message],
            ],
        ], 422));
    }
}
