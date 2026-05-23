<?php

namespace App\Modules\Merchant\Controllers;

use App\Models\Merchant;
use App\Modules\Dashboard\Resources\DashboardSummaryResource;
use App\Modules\Dashboard\Services\DashboardSummaryService;
use App\Modules\Merchant\Requests\StoreMerchantRequest;
use App\Modules\Merchant\Requests\UpdateMerchantRequest;
use App\Modules\Merchant\Resources\MerchantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MerchantController
{
    public function __construct(
        private readonly DashboardSummaryService $dashboardSummaryService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'pricing_plan_id' => [
                'nullable',
                Rule::exists('pricing_plans', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ]);

        $merchants = Merchant::query()
            ->with('pricingPlan')
            ->when(isset($validated['pricing_plan_id']), fn ($query) => $query->where('pricing_plan_id', $validated['pricing_plan_id']))
            ->latest()
            ->get();

        return MerchantResource::collection($merchants);
    }

    public function store(StoreMerchantRequest $request): JsonResponse
    {
        $merchant = Merchant::create([
            'name' => $request->string('name')->toString(),
            'company_name' => $request->input('company_name'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'status' => $request->input('status', 'active'),
            'pricing_plan_id' => $request->input('pricing_plan_id'),
        ]);

        return (new MerchantResource($merchant->load('pricingPlan')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Merchant $merchant): MerchantResource
    {
        return new MerchantResource($merchant->load('pricingPlan'));
    }

    public function update(UpdateMerchantRequest $request, Merchant $merchant): MerchantResource
    {
        $merchant->update($request->validated());

        return new MerchantResource($merchant->fresh()->load('pricingPlan'));
    }

    public function destroy(Merchant $merchant): JsonResponse
    {
        $merchant->delete();

        return response()->json([
            'message' => 'Merchant deleted successfully',
        ]);
    }

    public function dashboardSummary(Request $request, Merchant $merchant): DashboardSummaryResource
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'assigned_driver_id' => [
                'nullable',
                Rule::exists('drivers', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ]);

        return new DashboardSummaryResource(
            $this->dashboardSummaryService->getMerchantSummary($merchant->id, $validated)
        );
    }
}
