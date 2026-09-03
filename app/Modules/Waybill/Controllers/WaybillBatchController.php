<?php

namespace App\Modules\Waybill\Controllers;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Label\Requests\RenderLabelRequest;
use App\Modules\Label\Services\LabelDataBuilderService;
use App\Modules\Label\Services\LabelRenderService;
use App\Modules\Waybill\Enums\WaybillStatus;
use App\Modules\Waybill\Models\Waybill;
use App\Modules\Waybill\Models\WaybillBatch;
use App\Modules\Waybill\Requests\GenerateWaybillBatchRequest;
use App\Modules\Waybill\Requests\ListWaybillBatchesRequest;
use App\Modules\Waybill\Resources\WaybillBatchResource;
use App\Modules\Waybill\Services\GenerateWaybillBatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class WaybillBatchController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly GenerateWaybillBatchService $batchService,
        private readonly LabelDataBuilderService $labelDataBuilderService,
        private readonly LabelRenderService $labelRenderService,
    ) {}

    public function index(ListWaybillBatchesRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = $this->baseQuery()
            ->when(isset($validated['created_from']), fn ($builder) => $builder->whereDate('generated_at', '>=', $validated['created_from']))
            ->when(isset($validated['created_to']), fn ($builder) => $builder->whereDate('generated_at', '<=', $validated['created_to']))
            ->when(isset($validated['generated_by']), fn ($builder) => $builder->where('generated_by', $validated['generated_by']))
            ->when(isset($validated['search']), function ($builder) use ($validated): void {
                $search = $validated['search'];
                $builder->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('batch_number', 'like', '%'.$search.'%')
                        ->orWhere('first_waybill_number', 'like', '%'.$search.'%')
                        ->orWhere('last_waybill_number', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            })
            ->latest('generated_at');

        $summary = Waybill::query()
            ->selectRaw('COUNT(*) as total_generated')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available', [WaybillStatus::AVAILABLE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assigned', [WaybillStatus::ASSIGNED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as void', [WaybillStatus::VOID])
            ->first();

        return WaybillBatchResource::collection($query->paginate((int) ($validated['per_page'] ?? 25)))
            ->additional([
                'summary' => [
                    'total_generated' => (int) ($summary?->total_generated ?? 0),
                    'available' => (int) ($summary?->available ?? 0),
                    'assigned' => (int) ($summary?->assigned ?? 0),
                    'void' => (int) ($summary?->void ?? 0),
                ],
            ]);
    }

    public function store(GenerateWaybillBatchRequest $request): JsonResponse
    {
        return (new WaybillBatchResource(
            $this->batchService->generate($request->validated(), (int) $request->user()->id)
        ))->response()->setStatusCode(201);
    }

    public function show(WaybillBatch $batch): WaybillBatchResource
    {
        return new WaybillBatchResource(
            $this->baseQuery()->with(['waybills.order', 'waybills.shipment'])->findOrFail($batch->id)
        );
    }

    public function print(RenderLabelRequest $request, WaybillBatch $batch): Response
    {
        $batch->load(['waybills' => fn ($query) => $query->orderBy('id')]);
        $labels = $batch->waybills
            ->map(fn ($waybill): array => $this->labelDataBuilderService->forWaybill($waybill))
            ->all();

        $this->auditLogService->log(
            AuditEventType::WAYBILL_BATCH_REPRINTED,
            'printed',
            'waybill_batch',
            $batch->id,
            [
                'user' => $request->user(),
                'metadata' => [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $batch->quantity,
                    'first_waybill_number' => $batch->first_waybill_number,
                    'last_waybill_number' => $batch->last_waybill_number,
                ],
                'notes' => 'Waybill batch printed or reprinted.',
            ],
        );

        return $this->labelRenderService->renderLabels($labels, 'waybill', [
            ...$request->validated(),
            'filename' => 'waybill-batch-'.$batch->batch_number,
        ]);
    }

    public function simplePrint(RenderLabelRequest $request, WaybillBatch $batch): Response
    {
        $batch->load(['waybills' => fn ($query) => $query->orderBy('id')]);
        $labels = $batch->waybills
            ->map(fn ($waybill): array => $this->labelDataBuilderService->forSimpleWaybill($waybill))
            ->all();

        $this->auditLogService->log(
            AuditEventType::WAYBILL_BATCH_REPRINTED,
            'printed',
            'waybill_batch',
            $batch->id,
            [
                'user' => $request->user(),
                'metadata' => [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $batch->quantity,
                    'first_waybill_number' => $batch->first_waybill_number,
                    'last_waybill_number' => $batch->last_waybill_number,
                    'label_mode' => 'simple',
                ],
                'notes' => 'Simple waybill batch printed or reprinted.',
            ],
        );

        return $this->labelRenderService->renderSimpleWaybillLabels($labels, [
            ...$request->validated(),
            'filename' => 'simple-waybill-batch-'.$batch->batch_number,
        ]);
    }

    private function baseQuery(): Builder
    {
        return WaybillBatch::query()
            ->with('generatedBy')
            ->withCount([
                'waybills as available_count' => fn ($query) => $query->where('status', WaybillStatus::AVAILABLE),
                'waybills as assigned_count' => fn ($query) => $query->where('status', WaybillStatus::ASSIGNED),
                'waybills as void_count' => fn ($query) => $query->where('status', WaybillStatus::VOID),
            ]);
    }
}
