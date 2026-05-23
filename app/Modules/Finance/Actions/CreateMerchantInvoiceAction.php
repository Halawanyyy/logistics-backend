<?php

namespace App\Modules\Finance\Actions;

use App\Models\Merchant;
use App\Modules\Finance\Enums\MerchantInvoiceStatus;
use App\Modules\Finance\Enums\MerchantWalletTransactionStatus;
use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Finance\Models\MerchantWalletTransaction;
use App\Modules\Finance\Services\FinanceSummaryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateMerchantInvoiceAction
{
    public function __construct(
        private readonly FinanceSummaryService $financeSummaryService,
        private readonly GenerateMerchantInvoiceNumberAction $generateMerchantInvoiceNumberAction,
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function execute(Merchant $merchant, array $validated, ?int $createdBy): MerchantInvoice
    {
        return DB::transaction(function () use ($merchant, $validated, $createdBy) {
            $status = $validated['status'] ?? MerchantInvoiceStatus::DRAFT;

            $invoice = MerchantInvoice::create([
                'merchant_id' => $merchant->id,
                'invoice_number' => $this->generateMerchantInvoiceNumberAction->execute(),
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
                'total_cod' => 0,
                'total_shipping_fees' => 0,
                'total_warehouse_charges' => 0,
                'total_payable' => 0,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $createdBy,
                'issued_at' => $status === MerchantInvoiceStatus::ISSUED ? now() : null,
            ]);

            return $this->refresh($invoice, $merchant, $validated);
        });
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function refresh(MerchantInvoice $invoice, Merchant $merchant, array $validated): MerchantInvoice
    {
        return DB::transaction(function () use ($invoice, $merchant, $validated) {
            $invoice->items()->delete();

            $walletTransactions = $this->walletTransactions($merchant, $validated);

            if ($walletTransactions->isNotEmpty()) {
                foreach ($walletTransactions as $transaction) {
                    $description = $transaction->reason
                        ?? str($transaction->transaction_type)->replace('_', ' ')->title()->toString();

                    $invoice->items()->create([
                        'item_type' => $transaction->transaction_type,
                        'description' => $description,
                        'quantity' => 1,
                        'unit_amount' => $transaction->amount,
                        'total_amount' => $transaction->amount,
                        'source_type' => $transaction->source_type,
                        'source_id' => $transaction->source_id,
                    ]);
                }
            } else {
                $totals = $this->financeSummaryService->calculateMerchantInvoiceTotals($merchant, [
                    'date_from' => $validated['period_start'] ?? null,
                    'date_to' => $validated['period_end'] ?? null,
                ]);

                $fallbackItems = [
                    ['item_type' => 'cod_collected', 'description' => 'Legacy COD collected total', 'total_amount' => $totals['total_cod']],
                    ['item_type' => 'shipping_fee', 'description' => 'Legacy shipping fee total', 'total_amount' => $totals['total_shipping_fees']],
                    ['item_type' => 'warehouse_charge', 'description' => 'Legacy warehouse charge total', 'total_amount' => $totals['total_warehouse_charges']],
                ];

                foreach ($fallbackItems as $item) {
                    if ((float) $item['total_amount'] <= 0) {
                        continue;
                    }

                    $invoice->items()->create([
                        'item_type' => $item['item_type'],
                        'description' => $item['description'],
                        'quantity' => 1,
                        'unit_amount' => $item['total_amount'],
                        'total_amount' => $item['total_amount'],
                        'source_type' => null,
                        'source_id' => null,
                    ]);
                }
            }

            $invoice->load('items');
            $totals = $this->deriveTotalsFromItems($invoice);
            $status = $validated['status'] ?? $invoice->status;

            $invoice->update([
                'period_start' => $validated['period_start'] ?? $invoice->period_start,
                'period_end' => $validated['period_end'] ?? $invoice->period_end,
                'total_cod' => $totals['total_cod'],
                'total_shipping_fees' => $totals['total_shipping_fees'],
                'total_warehouse_charges' => $totals['total_warehouse_charges'],
                'total_payable' => $totals['total_payable'],
                'status' => $status,
                'notes' => $validated['notes'] ?? $invoice->notes,
                'issued_at' => $status === MerchantInvoiceStatus::ISSUED && $invoice->issued_at === null
                    ? now()
                    : $invoice->issued_at,
            ]);

            return $invoice->fresh()->load(['merchant', 'createdBy', 'items']);
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @return Collection<int, MerchantWalletTransaction>
     */
    private function walletTransactions(Merchant $merchant, array $validated): Collection
    {
        return MerchantWalletTransaction::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', MerchantWalletTransactionStatus::APPROVED)
            ->when(isset($validated['period_start']) && $validated['period_start'] !== null, fn ($query) => $query->whereDate('created_at', '>=', $validated['period_start']))
            ->when(isset($validated['period_end']) && $validated['period_end'] !== null, fn ($query) => $query->whereDate('created_at', '<=', $validated['period_end']))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{total_cod:string,total_shipping_fees:string,total_warehouse_charges:string,total_payable:string}
     */
    private function deriveTotalsFromItems(MerchantInvoice $invoice): array
    {
        $shippingFeeTypes = ['shipping_fee', 'return_fee', 'swap_fee'];
        $warehouseFeeTypes = ['storage_fee', 'pick_fee', 'packing_fee', 'warehouse_charge'];
        $creditTypes = ['cod_collected', 'manual_deposit', 'refund'];

        $totalCod = $invoice->items
            ->where('item_type', 'cod_collected')
            ->sum(fn ($item): float => (float) $item->total_amount);

        $totalShippingFees = $invoice->items
            ->whereIn('item_type', $shippingFeeTypes)
            ->sum(fn ($item): float => (float) $item->total_amount);

        $totalWarehouseCharges = $invoice->items
            ->whereIn('item_type', $warehouseFeeTypes)
            ->sum(fn ($item): float => (float) $item->total_amount);

        $credits = $invoice->items
            ->whereIn('item_type', $creditTypes)
            ->sum(fn ($item): float => (float) $item->total_amount);

        $debits = $invoice->items
            ->reject(fn ($item) => in_array($item->item_type, $creditTypes, true))
            ->sum(fn ($item): float => (float) $item->total_amount);

        return [
            'total_cod' => number_format((float) $totalCod, 2, '.', ''),
            'total_shipping_fees' => number_format((float) $totalShippingFees, 2, '.', ''),
            'total_warehouse_charges' => number_format((float) $totalWarehouseCharges, 2, '.', ''),
            'total_payable' => number_format((float) ($credits - $debits), 2, '.', ''),
        ];
    }
}
