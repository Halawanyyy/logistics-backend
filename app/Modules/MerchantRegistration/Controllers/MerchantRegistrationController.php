<?php

namespace App\Modules\MerchantRegistration\Controllers;

use App\Modules\MerchantRegistration\Actions\RegisterMerchantAction;
use App\Modules\MerchantRegistration\Requests\RegisterMerchantRequest;
use Illuminate\Http\JsonResponse;

class MerchantRegistrationController
{
    public function store(RegisterMerchantRequest $request, RegisterMerchantAction $registerMerchant): JsonResponse
    {
        $merchant = $registerMerchant->execute($request->validated());

        return response()->json([
            'message' => 'Registration submitted successfully. Your account is waiting for administrator approval.',
            'data' => [
                'merchant_id' => $merchant->id,
                'company_name' => $merchant->company_name,
                'status' => $merchant->status,
                'registered_at' => $merchant->created_at,
            ],
        ], 201);
    }
}
