<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseShelf;
use App\Modules\Warehouse\Models\WarehouseZone;
use App\Modules\Warehouse\Resources\WarehouseShelfResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WarehouseShelfController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'zone_id' => ['nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'status' => ['nullable', 'string'],
        ]);

        $shelves = WarehouseShelf::query()
            ->with(['warehouse', 'zone'])
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['zone_id']), fn ($query) => $query->where('warehouse_zone_id', $validated['zone_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return WarehouseShelfResource::collection($shelves);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_zone_id' => ['nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);

        $this->validateZoneWarehouse($validated['warehouse_id'], $validated['warehouse_zone_id'] ?? null);
        $this->validateUniqueCode($validated['warehouse_id'], $validated['code']);

        $shelf = WarehouseShelf::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);

        return (new WarehouseShelfResource($shelf->load(['warehouse', 'zone'])))->response()->setStatusCode(201);
    }

    public function show(WarehouseShelf $warehouseShelf): WarehouseShelfResource
    {
        return new WarehouseShelfResource($warehouseShelf->load(['warehouse', 'zone']));
    }

    public function update(Request $request, WarehouseShelf $warehouseShelf): WarehouseShelfResource
    {
        $validated = $request->validate([
            'warehouse_id' => ['sometimes', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'warehouse_zone_id' => ['sometimes', 'nullable', Rule::exists('warehouse_zones', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? $warehouseShelf->warehouse_id;
        $zoneId = array_key_exists('warehouse_zone_id', $validated) ? $validated['warehouse_zone_id'] : $warehouseShelf->warehouse_zone_id;
        $code = $validated['code'] ?? $warehouseShelf->code;
        $this->validateZoneWarehouse($warehouseId, $zoneId);
        $this->validateUniqueCode($warehouseId, $code, $warehouseShelf->id);

        $warehouseShelf->update($validated);

        return new WarehouseShelfResource($warehouseShelf->fresh()->load(['warehouse', 'zone']));
    }

    public function destroy(WarehouseShelf $warehouseShelf): JsonResponse
    {
        $warehouseShelf->delete();

        return response()->json(['message' => 'Warehouse shelf deleted successfully']);
    }

    private function validateZoneWarehouse(int $warehouseId, ?int $zoneId): void
    {
        if ($zoneId === null) {
            return;
        }

        $exists = WarehouseZone::query()
            ->whereKey($zoneId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (! $exists) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['warehouse_zone_id' => ['The selected zone does not belong to the selected warehouse.']],
            ], 422));
        }
    }

    private function validateUniqueCode(int $warehouseId, string $code, ?int $ignoreId = null): void
    {
        $exists = WarehouseShelf::query()
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
