<?php

namespace App\Modules\Waybill\Services;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Waybill\Enums\WaybillSource;
use App\Modules\Waybill\Enums\WaybillStatus;
use App\Modules\Waybill\Models\Waybill;
use App\Modules\Waybill\Models\WaybillBatch;
use Illuminate\Support\Facades\DB;

class GenerateWaybillBatchService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NumberSequenceService $numberSequenceService,
        private readonly WaybillNumberGenerator $numberGenerator,
    ) {}

    /** @param array<string, mixed> $data */
    public function generate(array $data, int $userId): WaybillBatch
    {
        return DB::transaction(function () use ($data, $userId): WaybillBatch {
            $quantity = (int) $data['quantity'];
            $range = $this->numberGenerator->reserveRange($quantity);
            $batchNumber = $this->numberSequenceService->reserve('waybill_batch', 1)['numbers'][0];
            $generatedAt = now();

            $batch = WaybillBatch::query()->create([
                'batch_number' => $batchNumber,
                'quantity' => $quantity,
                'first_waybill_number' => $range['numbers'][0],
                'last_waybill_number' => $range['numbers'][$quantity - 1],
                'label_template' => $data['label_template'] ?? 'default',
                'generated_by' => $userId,
                'generated_at' => $generatedAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $rows = array_map(fn (string $number): array => [
                'waybill_number' => $number,
                'barcode' => $number,
                'status' => WaybillStatus::AVAILABLE,
                'source' => WaybillSource::PREPRINTED,
                'batch_id' => $batch->id,
                'generated_by' => $userId,
                'generated_at' => $generatedAt,
                'created_at' => $generatedAt,
                'updated_at' => $generatedAt,
            ], $range['numbers']);

            foreach (array_chunk($rows, 250) as $chunk) {
                Waybill::query()->insert($chunk);
            }

            $this->auditLogService->log(
                AuditEventType::WAYBILL_BATCH_GENERATED,
                'generated',
                'waybill_batch',
                $batch->id,
                [
                    'user_id' => $userId,
                    'metadata' => [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'quantity' => $quantity,
                        'first_waybill_number' => $batch->first_waybill_number,
                        'last_waybill_number' => $batch->last_waybill_number,
                    ],
                    'notes' => $batch->notes ?? 'Pre-printed waybill batch generated.',
                ],
            );

            return $batch->load(['generatedBy', 'waybills']);
        });
    }
}
