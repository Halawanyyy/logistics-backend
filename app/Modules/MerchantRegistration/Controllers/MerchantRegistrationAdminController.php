<?php

namespace App\Modules\MerchantRegistration\Controllers;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantRegistration\Requests\ApproveMerchantRegistrationRequest;
use App\Modules\MerchantRegistration\Requests\ListMerchantRegistrationRequestsRequest;
use App\Modules\MerchantRegistration\Requests\RejectMerchantRegistrationRequest;
use App\Modules\MerchantRegistration\Resources\MerchantRegistrationRequestDetailResource;
use App\Modules\MerchantRegistration\Resources\MerchantRegistrationRequestResource;
use App\Modules\MerchantRegistration\Services\MerchantRegistrationReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MerchantRegistrationAdminController
{
    public function __construct(
        private readonly MerchantRegistrationReviewService $reviewService,
    ) {}

    public function index(ListMerchantRegistrationRequestsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return MerchantRegistrationRequestResource::collection(
            $this->reviewService->query($validated)->paginate((int) ($validated['per_page'] ?? 25))
        );
    }

    public function show(Merchant $merchant): MerchantRegistrationRequestDetailResource
    {
        return new MerchantRegistrationRequestDetailResource($this->reviewService->find($merchant));
    }

    public function approve(ApproveMerchantRegistrationRequest $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validated();
        /** @var User $reviewer */
        $reviewer = $request->user();
        $reviewed = $this->reviewService->approve(
            $merchant,
            $reviewer,
            isset($validated['pricing_plan_id']) ? (int) $validated['pricing_plan_id'] : null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Merchant registration approved successfully.',
            'data' => [
                'merchant_id' => $reviewed->id,
                'status' => $reviewed->status,
            ],
        ]);
    }

    public function reject(RejectMerchantRegistrationRequest $request, Merchant $merchant): JsonResponse
    {
        /** @var User $reviewer */
        $reviewer = $request->user();
        $reviewed = $this->reviewService->reject($merchant, $reviewer, $request->validated('reason'));

        return response()->json([
            'message' => 'Merchant registration rejected.',
            'data' => [
                'merchant_id' => $reviewed->id,
                'status' => $reviewed->status,
                'reason' => $reviewed->rejection_reason,
            ],
        ]);
    }
}
