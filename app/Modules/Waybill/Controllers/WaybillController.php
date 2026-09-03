<?php

namespace App\Modules\Waybill\Controllers;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Waybill\Enums\WaybillSource;
use App\Modules\Waybill\Enums\WaybillStatus;
use App\Modules\Waybill\Models\Waybill;
use App\Modules\Waybill\Requests\ValidateWaybillRequest;
use App\Modules\Waybill\Requests\VoidWaybillRequest;
use App\Modules\Waybill\Resources\WaybillResource;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WaybillController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
    ) {}

    public function validateWaybill(ValidateWaybillRequest $request): JsonResponse
    {
        $value = $request->validated('waybill_number');
        $waybill = Waybill::query()
            ->where('waybill_number', $value)
            ->orWhere('barcode', $value)
            ->first();

        if ($waybill === null) {
            return response()->json([
                'code' => 'WAYBILL_NOT_FOUND',
                'message' => 'Waybill not found.',
                'valid' => false,
            ], 404);
        }

        $valid = $waybill->source === WaybillSource::PREPRINTED
            && $waybill->status === WaybillStatus::AVAILABLE
            && $waybill->order_id === null;

        return response()->json([
            'valid' => $valid,
            'waybill' => new WaybillResource($waybill),
        ]);
    }

    public function label(RenderLabelRequest $request, Waybill $waybill): Response
    {
        return $this->labelRenderService->renderLabel(
            $this->labelDataBuilderService->forWaybill($waybill->load(['order', 'shipment'])),
            [
                ...$request->validated(),
                'filename' => 'waybill-'.$waybill->waybill_number,
            ],
        );
    }

    public function simpleLabel(RenderLabelRequest $request, Waybill $waybill): Response
    {
        return $this->labelRenderService->renderSimpleWaybillLabels(
            [$this->labelDataBuilderService->forSimpleWaybill($waybill)],
            [
                ...$request->validated(),
                'filename' => 'simple-waybill-'.$waybill->waybill_number,
            ],
        );
    }

    public function void(VoidWaybillRequest $request, Waybill $waybill): WaybillResource
    {
        $waybill = DB::transaction(function () use ($request, $waybill): Waybill {
            $locked = Waybill::query()->lockForUpdate()->findOrFail($waybill->id);

            if ($locked->status === WaybillStatus::VOID) {
                $this->fail('WAYBILL_VOID', 'This waybill has already been voided.', 409);
            }

            if ($locked->status !== WaybillStatus::AVAILABLE || $locked->order_id !== null) {
                $this->fail('WAYBILL_ALREADY_ASSIGNED', 'Assigned waybills cannot be voided.', 409);
            }

            $locked->update([
                'status' => WaybillStatus::VOID,
                'voided_by' => $request->user()?->id,
                'voided_at' => now(),
                'void_reason' => $request->validated('reason'),
            ]);

            $this->auditLogService->log(
                AuditEventType::WAYBILL_VOIDED,
                'voided',
                'waybill',
                $locked->id,
                [
                    'user' => $request->user(),
                    'old_values' => ['status' => WaybillStatus::AVAILABLE],
                    'new_values' => ['status' => WaybillStatus::VOID],
                    'metadata' => [
                        'waybill_id' => $locked->id,
                        'waybill_number' => $locked->waybill_number,
                        'voided_at' => $locked->voided_at?->toISOString(),
                        'reason' => $locked->void_reason,
                    ],
                    'notes' => 'Available waybill permanently voided.',
                ],
            );

            return $locked->fresh();
        });

        return new WaybillResource($waybill);
    }

    private function fail(string $code, string $message, int $status): never
    {
        throw new HttpResponseException(response()->json([
            'code' => $code,
            'message' => $message,
        ], $status));
    }
}
