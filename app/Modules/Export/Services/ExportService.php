<?php

namespace App\Modules\Export\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Driver\Models\DriverManifest;
use App\Modules\Export\Models\ExportLog;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantPayout;
use App\Modules\Finance\Models\TreasuryTransaction;
use App\Modules\Finance\Services\FinanceReportService;
use App\Modules\MerchantPortal\Models\MerchantInboundStockRequest;
use App\Modules\Order\Models\Order;
use App\Modules\Settings\Services\SystemSettingService;
use App\Modules\Shipment\Models\Shipment;
use App\Modules\Warehouse\Models\InventoryStock;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportService
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_TYPES = [
        'orders',
        'shipments',
        'driver_manifests',
        'merchant_wallet_transactions',
        'wallet_transactions',
        'stock_movements',
        'invoices',
        'payouts',
        'inbound_stock_requests',
        'finance_reports',
        'warehouse_inventory',
        'treasury_transactions',
        'audit_logs',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly FinanceReportService $financeReportService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function generate(string $type, string $format, array $filters = [], ?int $generatedBy = null, ?int $merchantId = null): ExportLog
    {
        $type = strtolower($type);
        $format = strtolower($format);

        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Unsupported export type.',
            ], 422));
        }

        if ($format !== 'csv') {
            throw new HttpResponseException(response()->json([
                'message' => 'Only csv export is currently supported.',
            ], 422));
        }

        $rows = $this->rowsForType($type, $filters);
        $fileName = $type.'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.csv';

        Storage::disk('exports')->put($fileName, $this->toCsv($rows));

        $exportLog = ExportLog::query()->create([
            'merchant_id' => $merchantId,
            'export_type' => $type,
            'format' => $format,
            'filters' => $filters === [] ? null : $filters,
            'status' => 'completed',
            'file_path' => 'exports/'.$fileName,
            'generated_by' => $generatedBy,
            'generated_at' => now(),
        ]);

        $this->auditLogService->log(
            AuditEventType::EXPORT_GENERATED,
            'generated',
            'export_log',
            $exportLog->id,
            [
                'user_id' => $generatedBy,
                'metadata' => [
                    'export_type' => $type,
                    'format' => $format,
                    'filters' => $filters,
                ],
                'notes' => 'Export generated successfully.',
            ],
        );

        return $exportLog->load('generatedBy');
    }

    /**
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }

    public function diskPath(string $filePath): string
    {
        $normalized = str_replace('\\', '/', str_starts_with($filePath, 'exports/')
            ? Str::after($filePath, 'exports/')
            : $filePath);

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || str_contains($normalized, '../')
            || str_contains($normalized, '..\\')
        ) {
            throw new HttpResponseException(response()->json([
                'message' => 'Export file not found.',
            ], 404));
        }

        return ltrim($normalized, '/');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function rowsForType(string $type, array $filters): array
    {
        return match ($type) {
            'orders' => $this->orderRows($filters),
            'shipments' => $this->shipmentRows($filters),
            'driver_manifests' => $this->driverManifestRows($filters),
            'merchant_wallet_transactions' => $this->walletRows($filters),
            'wallet_transactions' => $this->walletRows($filters),
            'stock_movements' => $this->stockMovementRows($filters),
            'invoices' => $this->invoiceRows($filters),
            'payouts' => $this->payoutRows($filters),
            'inbound_stock_requests' => $this->inboundRequestRows($filters),
            'finance_reports' => $this->financeReportRows($filters),
            'warehouse_inventory' => $this->warehouseInventoryRows($filters),
            'treasury_transactions' => $this->treasuryRows($filters),
            'audit_logs' => $this->auditLogRows($filters),
            default => [],
        };
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new HttpResponseException(response()->json([
                'message' => 'Failed to prepare export stream.',
            ], 500));
        }

        $headers = array_keys($rows[0] ?? ['message' => 'No data found for the selected export filters.']);
        fputcsv($stream, $headers);

        if ($rows === []) {
            fputcsv($stream, ['No data found for the selected export filters.']);
        } else {
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->normalizeCsvValue(...), $row));
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private function normalizeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function orderRows(array $filters): array
    {
        return Order::query()
            ->with('merchant')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'merchant_name' => $order->merchant?->name,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'status' => $order->status,
                'order_type' => $order->order_type,
                'payment_type' => $order->payment_type,
                'fulfillment_type' => $order->fulfillment_type,
                'cod_amount' => $order->cod_amount,
                'shipping_fee' => $order->shipping_fee,
                'created_at' => $order->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function shipmentRows(array $filters): array
    {
        return Shipment::query()
            ->with(['merchant', 'order', 'assignedDriver'])
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest()
            ->get()
            ->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'order_number' => $shipment->order?->order_number,
                'merchant_name' => $shipment->merchant?->name,
                'customer_name' => $shipment->customer_name,
                'customer_phone' => $shipment->customer_phone,
                'status' => $shipment->status,
                'assigned_driver_name' => $shipment->assignedDriver?->name,
                'cod_amount' => $shipment->cod_amount,
                'shipping_fee' => $shipment->shipping_fee,
                'created_at' => $shipment->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function driverManifestRows(array $filters): array
    {
        return DriverManifest::query()
            ->with('driver')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('manifest_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('manifest_date', '<=', $filters['date_to']))
            ->when(isset($filters['driver_id']), fn (Builder $query) => $query->where('driver_id', $filters['driver_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('manifest_date')
            ->get()
            ->map(fn (DriverManifest $manifest) => [
                'id' => $manifest->id,
                'manifest_number' => $manifest->manifest_number,
                'driver_name' => $manifest->driver?->name,
                'manifest_date' => $manifest->manifest_date?->format('Y-m-d'),
                'status' => $manifest->status,
                'total_shipments' => $manifest->total_shipments,
                'expected_cod_amount' => $manifest->expected_cod_amount,
                'actual_received_amount' => $manifest->actual_received_amount,
                'difference_amount' => $manifest->difference_amount,
                'created_at' => $manifest->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function walletRows(array $filters): array
    {
        return MerchantWalletTransaction::query()
            ->with('merchant')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['transaction_type']), fn (Builder $query) => $query->where('transaction_type', $filters['transaction_type']))
            ->latest()
            ->get()
            ->map(fn (MerchantWalletTransaction $transaction) => [
                'id' => $transaction->id,
                'merchant_name' => $transaction->merchant?->name,
                'transaction_number' => $transaction->transaction_number,
                'transaction_type' => $transaction->transaction_type,
                'direction' => $transaction->direction,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'source_type' => $transaction->source_type,
                'source_id' => $transaction->source_id,
                'reference_number' => $transaction->reference_number,
                'created_at' => $transaction->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function financeReportRows(array $filters): array
    {
        $reportType = (string) ($filters['report_type'] ?? 'overview');

        $report = match ($reportType) {
            'daily_cash' => $this->financeReportService->getDailyCashReport($filters),
            'merchant_ledger' => $this->financeReportService->getMerchantLedgerReport($filters),
            'driver_discrepancies' => $this->financeReportService->getDriverDiscrepanciesReport($filters),
            'warehouse_revenue' => $this->financeReportService->getWarehouseRevenueReport($filters),
            'profit_loss' => $this->financeReportService->getProfitLossReport($filters),
            default => $this->financeReportService->getOverviewReport($filters),
        };

        return $this->flattenReport($reportType, $report);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array<string, scalar|null>>
     */
    private function flattenReport(string $reportType, array $report): array
    {
        $rows = [];

        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $rows[] = [
                    'report_type' => $reportType,
                    'metric' => $key,
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];

                continue;
            }

            $rows[] = [
                'report_type' => $reportType,
                'metric' => (string) $key,
                'value' => $value === null ? null : (string) $value,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function warehouseInventoryRows(array $filters): array
    {
        return InventoryStock::query()
            ->with(['warehouse', 'warehouseProduct.merchant', 'warehouseBin'])
            ->when(isset($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['merchant_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('warehouseProduct', fn (Builder $productQuery) => $productQuery->where('merchant_id', $filters['merchant_id']));
            })
            ->latest()
            ->get()
            ->map(fn (InventoryStock $stock) => [
                'id' => $stock->id,
                'warehouse_name' => $stock->warehouse?->name,
                'merchant_name' => $stock->warehouseProduct?->merchant?->name,
                'product_name' => $stock->warehouseProduct?->name,
                'sku' => $stock->warehouseProduct?->sku,
                'bin_name' => $stock->warehouseBin?->name,
                'quantity_available' => $stock->quantity_available,
                'quantity_reserved' => $stock->quantity_reserved,
                'quantity_damaged' => $stock->quantity_damaged,
                'created_at' => $stock->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function stockMovementRows(array $filters): array
    {
        return StockMovement::query()
            ->with(['warehouse', 'warehouseProduct.merchant'])
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), function (Builder $query) use ($filters): void {
                $query->whereHas('warehouseProduct', fn (Builder $productQuery) => $productQuery->where('merchant_id', $filters['merchant_id']));
            })
            ->when(isset($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->when(isset($filters['movement_type']), fn (Builder $query) => $query->where('type', $filters['movement_type']))
            ->latest()
            ->get()
            ->map(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'merchant_name' => $movement->warehouseProduct?->merchant?->name,
                'warehouse_name' => $movement->warehouse?->name,
                'product_name' => $movement->warehouseProduct?->name,
                'sku' => $movement->warehouseProduct?->sku,
                'movement_type' => $movement->type,
                'quantity' => $movement->quantity,
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
                'created_at' => $movement->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function invoiceRows(array $filters): array
    {
        return MerchantInvoice::query()
            ->with('merchant')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['invoice_number']), fn (Builder $query) => $query->where('invoice_number', 'like', '%'.$filters['invoice_number'].'%'))
            ->latest()
            ->get()
            ->map(fn (MerchantInvoice $invoice) => [
                'id' => $invoice->id,
                'merchant_name' => $invoice->merchant?->name,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'total_payable' => $invoice->total_payable,
                'issued_at' => $invoice->issued_at?->toISOString(),
                'created_at' => $invoice->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function payoutRows(array $filters): array
    {
        return MerchantPayout::query()
            ->with('merchant')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['payout_number']), fn (Builder $query) => $query->where('reference_number', 'like', '%'.$filters['payout_number'].'%'))
            ->latest()
            ->get()
            ->map(fn (MerchantPayout $payout) => [
                'id' => $payout->id,
                'merchant_name' => $payout->merchant?->name,
                'reference_number' => $payout->reference_number,
                'status' => $payout->status,
                'amount' => $payout->amount,
                'payment_method' => $payout->payment_method,
                'created_at' => $payout->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function inboundRequestRows(array $filters): array
    {
        return MerchantInboundStockRequest::query()
            ->with(['merchant', 'warehouse'])
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['merchant_id']), fn (Builder $query) => $query->where('merchant_id', $filters['merchant_id']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['request_number']), fn (Builder $query) => $query->where('request_number', 'like', '%'.$filters['request_number'].'%'))
            ->when(isset($filters['warehouse_id']), fn (Builder $query) => $query->where('warehouse_id', $filters['warehouse_id']))
            ->latest()
            ->get()
            ->map(fn (MerchantInboundStockRequest $request) => [
                'id' => $request->id,
                'merchant_name' => $request->merchant?->name,
                'warehouse_name' => $request->warehouse?->name,
                'request_number' => $request->request_number,
                'status' => $request->status,
                'expected_arrival_date' => $request->expected_arrival_date?->toDateString(),
                'created_at' => $request->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function treasuryRows(array $filters): array
    {
        return TreasuryTransaction::query()
            ->with('treasuryAccount')
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['treasury_account_id']), fn (Builder $query) => $query->where('treasury_account_id', $filters['treasury_account_id']))
            ->when(isset($filters['direction']), fn (Builder $query) => $query->where('direction', $filters['direction']))
            ->latest()
            ->get()
            ->map(fn (TreasuryTransaction $transaction) => [
                'id' => $transaction->id,
                'treasury_account_name' => $transaction->treasuryAccount?->name,
                'treasury_account_code' => $transaction->treasuryAccount?->code,
                'transaction_number' => $transaction->transaction_number,
                'direction' => $transaction->direction,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'source_type' => $transaction->source_type,
                'source_id' => $transaction->source_id,
                'created_at' => $transaction->created_at?->toISOString(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, scalar|null>>
     */
    private function auditLogRows(array $filters): array
    {
        $perPage = min(max((int) ($filters['per_page'] ?? $this->systemSettingService->defaultPaginationLimit(1000)), 1), 5000);

        return AuditLog::query()
            ->when(isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['event_type']), fn (Builder $query) => $query->where('event_type', $filters['event_type']))
            ->when(isset($filters['action']), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(isset($filters['auditable_type']), fn (Builder $query) => $query->where('auditable_type', $filters['auditable_type']))
            ->when(isset($filters['auditable_id']), fn (Builder $query) => $query->where('auditable_id', $filters['auditable_id']))
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $inner) use ($search): void {
                    $inner
                        ->where('user_name', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhere('event_type', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->limit($perPage)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user_name,
                'event_type' => $log->event_type,
                'action' => $log->action,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'notes' => $log->notes,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ])->values()->all();
    }
}
