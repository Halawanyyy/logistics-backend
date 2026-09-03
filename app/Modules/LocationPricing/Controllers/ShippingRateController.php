<?php

namespace App\Modules\LocationPricing\Controllers;

use App\Modules\LocationPricing\Models\ShippingRate;
use App\Modules\LocationPricing\Requests\CalculateShippingRateRequest;
use App\Modules\LocationPricing\Requests\StoreShippingRateRequest;
use App\Modules\LocationPricing\Requests\UpdateShippingRateRequest;
use App\Modules\LocationPricing\Resources\ShippingRateResource;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Order\Services\OrderShippingFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShippingRateController
{
    public function __construct(
        private readonly OrderShippingFeeService $orderShippingFeeService,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $shippingRates = ShippingRate::query()
            ->with(['governorate', 'area'])
            ->latest()
            ->get();

        return ShippingRateResource::collection($shippingRates);
    }

    public function store(StoreShippingRateRequest $request): JsonResponse
    {
        $shippingRate = ShippingRate::withTrashed()
            ->where('governorate_id', $request->integer('governorate_id'))
            ->when($request->input('area_id') !== null, fn ($q) => $q->where('area_id', $request->input('area_id')))
            ->when($request->input('area_id') === null, fn ($q) => $q->whereNull('area_id'))
            ->first();

        $data = [
            'governorate_id' => $request->integer('governorate_id'),
            'area_id' => $request->input('area_id'),
            'shipping_fee' => $request->input('shipping_fee'),
            'swap_fee' => $request->input('swap_fee'),
            'return_fee' => $request->input('return_fee'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ];

        if ($shippingRate) {
            if ($shippingRate->trashed()) {
                $shippingRate->restore();
            }
            $shippingRate->update($data);
        } else {
            $shippingRate = ShippingRate::create($data);
        }

        return (new ShippingRateResource($shippingRate->load(['governorate', 'area'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ShippingRate $shippingRate): ShippingRateResource
    {
        return new ShippingRateResource($shippingRate->load(['governorate', 'area']));
    }

    public function update(UpdateShippingRateRequest $request, ShippingRate $shippingRate): ShippingRateResource
    {
        $validated = $request->validated();

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $shippingRate->update($validated);

        return new ShippingRateResource($shippingRate->fresh()->load(['governorate', 'area']));
    }

    public function destroy(ShippingRate $shippingRate): JsonResponse
    {
        $shippingRate->delete();

        return response()->json([
            'message' => 'Shipping rate deleted successfully',
        ]);
    }

    public function restore(ShippingRate $shippingRate): JsonResponse
    {
        $shippingRate->restore();

        return response()->json([
            'message' => 'Shipping rate restored successfully',
        ]);
    }

    public function forceDestroy(ShippingRate $shippingRate): JsonResponse
    {
        $shippingRate->forceDelete();

        return response()->json([
            'message' => 'Shipping rate permanently deleted',
        ]);
    }

    public function calculate(CalculateShippingRateRequest $request): JsonResponse
    {
        $governorateId = $request->integer('governorate_id');
        $areaId = $request->input('area_id');
        $resolvedShipping = $this->orderShippingFeeService->resolve(
            $request->integer('merchant_id', 0),
            $governorateId,
            $areaId !== null ? (int) $areaId : null,
            $request->input('order_type', OrderType::DELIVERY),
        );

        if ($resolvedShipping['source'] === null) {
            return response()->json([
                'message' => 'Shipping rate not configured.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'merchant_id' => $request->input('merchant_id'),
                'order_type' => $request->input('order_type', OrderType::DELIVERY),
                'governorate_id' => $governorateId,
                'area_id' => $areaId,
                'shipping_fee' => number_format((float) $resolvedShipping['shipping_fee'], 2, '.', ''),
                'source' => $resolvedShipping['source'],
            ],
        ]);
    }
}
