<?php

namespace App\Modules\Warehouse\Controllers;

use App\Modules\Warehouse\Services\WarehouseKpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseKpiController
{
    public function __construct(
        private readonly WarehouseKpiService $warehouseKpiService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'merchant_id' => ['nullable', Rule::exists('merchants', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return response()->json([
            'data' => $this->warehouseKpiService->get($validated),
        ]);
    }
}
