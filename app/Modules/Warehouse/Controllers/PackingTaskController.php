<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\PackingTask;
use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Resources\PackingTaskResource;
use App\Modules\Warehouse\Services\PackingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PackingTaskController
{
    public function __construct(
        private readonly PackingTaskService $packingTaskService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'order_id' => ['nullable', Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'picking_list_id' => ['nullable', Rule::exists('picking_lists', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
        ]);

        $tasks = PackingTask::query()
            ->with(['pickingList', 'order', 'shipment', 'packedBy'])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['order_id']), fn ($query) => $query->where('order_id', $validated['order_id']))
            ->when(isset($validated['picking_list_id']), fn ($query) => $query->where('picking_list_id', $validated['picking_list_id']))
            ->latest()
            ->get();

        return PackingTaskResource::collection($tasks);
    }

    public function generate(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'picking_list_id' => ['required', Rule::exists('picking_lists', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
        ]);

        $tasks = $this->packingTaskService->generateFromPickingList(PickingList::query()->findOrFail($validated['picking_list_id']));

        return PackingTaskResource::collection($tasks);
    }

    public function show(PackingTask $packingTask): PackingTaskResource
    {
        return new PackingTaskResource($packingTask->load(['pickingList', 'order', 'shipment', 'packedBy']));
    }

    public function complete(Request $request, PackingTask $packingTask): PackingTaskResource
    {
        $validated = $request->validate([
            'package_weight' => ['nullable', 'numeric', 'min:0'],
            'package_length' => ['nullable', 'numeric', 'min:0'],
            'package_width' => ['nullable', 'numeric', 'min:0'],
            'package_height' => ['nullable', 'numeric', 'min:0'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        return new PackingTaskResource($this->packingTaskService->complete($packingTask, $validated, $request->user()?->id));
    }
}
