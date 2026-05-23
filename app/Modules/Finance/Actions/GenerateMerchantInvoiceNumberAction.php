<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\MerchantInvoice;
use App\Modules\Settings\Services\SystemSettingService;

class GenerateMerchantInvoiceNumberAction
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function execute(): string
    {
        $datePart = now()->format('Ymd');
        $configuredPrefix = (string) $this->systemSettingService->get('invoice_prefix', 'INV');
        $prefix = ($configuredPrefix !== '' ? $configuredPrefix : 'INV').'-'.$datePart.'-';

        $latestInvoiceNumber = MerchantInvoice::withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $nextSequence = $latestInvoiceNumber === null
            ? 1
            : ((int) substr($latestInvoiceNumber, -6)) + 1;

        return $prefix.str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
