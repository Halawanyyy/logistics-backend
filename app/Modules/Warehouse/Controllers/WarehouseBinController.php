<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseBin;
use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use App\Modules\Warehouse\Resources\WarehouseBinResource;
use App\Services\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WarehouseBinController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'zone_id' => ['nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'shelf_id' => ['nullable', Rule::exists('warehouse_shelves', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'status' => ['nullable', 'string'],
        ]);

        $bins = WarehouseBin::query()
            ->with(['warehouse', 'zone', 'shelf'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['zone_id']), fn ($query) => $query->where('warehouse_zone_id', $validated['zone_id']))
            ->when(isset($validated['shelf_id']), fn ($query) => $query->where('warehouse_shelf_id', $validated['shelf_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return WarehouseBinResource::collection($bins);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_zone_id' => ['nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_shelf_id' => ['nullable', Rule::exists('warehouse_shelves', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);

        $this->validateHierarchy($validated['warehouse_id'], $validated['warehouse_zone_id'] ?? null, $validated['warehouse_shelf_id'] ?? null);
        $this->validateUniqueCode($validated['warehouse_id'], $validated['code']);

        $barcode = $validated['barcode'] ?? $this->barcodeService->generateWarehouseBinBarcode();

        if (! $this->barcodeService->isUnique($barcode)) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['barcode' => ['The barcode has already been taken.']],
            ], 422));
        }

        $bin = WarehouseBin::create([
            ...$validated,
            'barcode' => $barcode,
            'status' => $validated['status'] ?? 'active',
        ]);

        return (new WarehouseBinResource($bin->load(['warehouse', 'zone', 'shelf'])))->response()->setStatusCode(201);
    }

    public function show(WarehouseBin $warehouseBin): WarehouseBinResource
    {
        return new WarehouseBinResource($warehouseBin->load(['warehouse', 'zone', 'shelf']));
    }

    public function update(Request $request, WarehouseBin $warehouseBin): WarehouseBinResource
    {
        $validated = $request->validate([
            'warehouse_id' => ['sometimes', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_zone_id' => ['sometimes', 'nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_shelf_id' => ['sometimes', 'nullable', Rule::exists('warehouse_shelves', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? $warehouseBin->warehouse_id;
        $zoneId = array_key_exists('warehouse_zone_id', $validated) ? $validated['warehouse_zone_id'] : $warehouseBin->warehouse_zone_id;
        $shelfId = array_key_exists('warehouse_shelf_id', $validated) ? $validated['warehouse_shelf_id'] : $warehouseBin->warehouse_shelf_id;
        $code = $validated['code'] ?? $warehouseBin->code;
        $this->validateHierarchy($warehouseId, $zoneId, $shelfId);
        $this->validateUniqueCode($warehouseId, $code, $warehouseBin->id);

        if (
            array_key_exists('barcode', $validated)
            && $validated['barcode'] !== null
            && ! $this->barcodeService->isUnique($validated['barcode'], 'warehouse_bin', $warehouseBin->id)
        ) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['barcode' => ['The barcode has already been taken.']],
            ], 422));
        }

        $warehouseBin->update($validated);

        return new WarehouseBinResource($warehouseBin->fresh()->load(['warehouse', 'zone', 'shelf']));
    }

    public function destroy(WarehouseBin $warehouseBin): JsonResponse
    {
        $warehouseBin->delete();

        return response()->json(['message' => 'Warehouse bin deleted successfully']);
    }

    private function validateHierarchy(int $warehouseId, ?int $zoneId, ?int $shelfId): void
    {
        if ($zoneId !== null) {
            $zoneExists = WarehouseZone::query()
                ->whereKey($zoneId)
                ->where('warehouse_id', $warehouseId)
                ->exists();

            if (! $zoneExists) {
                abort(response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['warehouse_zone_id' => ['The selected zone does not belong to the selected warehouse.']],
                ], 422));
            }
        }

        if ($shelfId !== null) {
            $shelfQuery = WarehouseShelf::query()
                ->whereKey($shelfId)
                ->where('warehouse_id', $warehouseId);

            if ($zoneId !== null) {
                $shelfQuery->where('warehouse_zone_id', $zoneId);
            }

            if (! $shelfQuery->exists()) {
                abort(response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['warehouse_shelf_id' => ['The selected shelf does not belong to the selected warehouse or zone.']],
                ], 422));
            }
        }
    }

    private function validateUniqueCode(int $warehouseId, string $code, ?int $ignoreId = null): void
    {
        $exists = WarehouseBin::query()
            ->where('warehouse_id', $warehouseId)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['code' => ['The code has already been taken for this warehouse.']],
            ], 422));
        }
    }
}
