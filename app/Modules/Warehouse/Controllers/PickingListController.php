<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\PickingList;
use App\Modules\Warehouse\Models\WarehouseProduct;
use App\Modules\Warehouse\Resources\PickingListResource;
use App\Modules\Warehouse\Services\PickingListService;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PickingListController
{
    public function __construct(
        private readonly PickingListService $pickingListService,
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'status' => ['nullable', 'string'],
        ]);

        $lists = PickingList::query()
            ->with(['warehouse', 'assignedTo', 'createdBy', 'items.order', 'items.orderItem', 'items.warehouseProduct', 'items.warehouseBin.zone', 'items.warehouseBin.shelf'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['assigned_to']), fn ($query) => $query->where('assigned_to', $validated['assigned_to']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return PickingListResource::collection($lists);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => [Rule::exists('orders', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $list = $this->pickingListService->generate($validated, $request->user()?->id);

        return (new PickingListResource($list))->response()->setStatusCode(201);
    }

    public function show(PickingList $pickingList): PickingListResource
    {
        return new PickingListResource($this->pickingListService->loadPickingList($pickingList));
    }

    public function scanPick(Request $request, PickingList $pickingList): PickingListResource|JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $lookup = $this->barcodeService->lookup($validated['barcode']);

        if ($lookup === null || $lookup['type'] !== 'warehouse_product') {
            return response()->json(['message' => 'Barcode not found.'], 404);
        }

        /** @var WarehouseProduct $product */
        $product = $lookup['model'];
        $list = $this->pickingListService->scanPick($pickingList, $product, (int) $validated['quantity']);

        return new PickingListResource($list);
    }

    public function cancel(PickingList $pickingList): PickingListResource
    {
        return new PickingListResource($this->pickingListService->cancel($pickingList));
    }
}
