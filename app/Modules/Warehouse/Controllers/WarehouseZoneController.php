<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Models\WarehouseZone;
use App\Modules\Warehouse\Resources\WarehouseZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WarehouseZoneController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'status' => ['nullable', 'string'],
        ]);

        $zones = WarehouseZone::query()
            ->with('warehouse')
            ->when(isset($validated['warehouse_id']), fn ($query) => $query->where('warehouse_id', $validated['warehouse_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return WarehouseZoneResource::collection($zones);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);

        $this->validateUniqueCode($validated['warehouse_id'], $validated['code']);

        $zone = WarehouseZone::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);

        return (new WarehouseZoneResource($zone->load('warehouse')))->response()->setStatusCode(201);
    }

    public function show(WarehouseZone $warehouseZone): WarehouseZoneResource
    {
        return new WarehouseZoneResource($warehouseZone->load('warehouse'));
    }

    public function update(Request $request, WarehouseZone $warehouseZone): WarehouseZoneResource
    {
        $validated = $request->validate([
            'warehouse_id' => ['sometimes', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $warehouseId = $validated['warehouse_id'] ?? $warehouseZone->warehouse_id;
        $code = $validated['code'] ?? $warehouseZone->code;
        $this->validateUniqueCode($warehouseId, $code, $warehouseZone->id);

        $warehouseZone->update($validated);

        return new WarehouseZoneResource($warehouseZone->fresh()->load('warehouse'));
    }

    public function destroy(WarehouseZone $warehouseZone): JsonResponse
    {
        $warehouseZone->delete();

        return response()->json(['message' => 'Warehouse zone deleted successfully']);
    }

    private function validateUniqueCode(int $warehouseId, string $code, ?int $ignoreId = null): void
    {
        $exists = WarehouseZone::query()
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
