<?php

namespace App\Modules\Waybill\Services;

use App\Modules\Waybill\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    /**
     * @return array{start:int,end:int,prefix:string,padding:int,numbers:list<string>}
     */
    public function reserve(string $key, int $quantity, ?string $prefix = null, ?int $padding = null): array
    {
        return DB::transaction(function () use ($key, $quantity, $prefix, $padding): array {
            $sequence = NumberSequence::query()
                ->where('key', $key)
                ->lockForUpdate()
                ->firstOrFail();

            $resolvedPrefix = $prefix ?? $sequence->prefix;
            $resolvedPadding = $padding ?? $sequence->padding;
            $start = $sequence->current_value + 1;
            $end = $sequence->current_value + $quantity;

            $sequence->update([
                'current_value' => $end,
                'prefix' => $resolvedPrefix,
                'padding' => $resolvedPadding,
            ]);

            $numbers = [];
            for ($value = $start; $value <= $end; $value++) {
                $numbers[] = $resolvedPrefix.str_pad((string) $value, $resolvedPadding, '0', STR_PAD_LEFT);
            }

            return [
                'start' => $start,
                'end' => $end,
                'prefix' => $resolvedPrefix,
                'padding' => $resolvedPadding,
                'numbers' => $numbers,
            ];
        });
    }
}
