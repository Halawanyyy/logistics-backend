<?php

namespace App\Modules\Waybill\Services;

use App\Modules\Settings\Services\SystemSettingService;

class WaybillNumberGenerator
{
    public function __construct(
        private readonly NumberSequenceService $numberSequenceService,
        private readonly SystemSettingService $systemSettingService,
    ) {}

    /**
     * @return array{start:int,end:int,prefix:string,padding:int,numbers:list<string>}
     */
    public function reserveRange(int $quantity): array
    {
        $prefix = (string) $this->systemSettingService->get('waybill_prefix', 'WB-');
        $padding = (int) $this->systemSettingService->get('waybill_padding', 6);

        return $this->numberSequenceService->reserve(
            'waybill',
            $quantity,
            $prefix !== '' ? $prefix : 'WB-',
            min(max($padding, 1), 20),
        );
    }

    public function next(): string
    {
        return $this->reserveRange(1)['numbers'][0];
    }
}
