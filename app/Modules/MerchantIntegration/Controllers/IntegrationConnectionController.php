<?php

namespace App\Modules\MerchantIntegration\Controllers;

use App\Models\Merchant;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationConnectionController
{
    public function me(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');
        /** @var MerchantIntegrationToken $token */
        $token = $request->attributes->get('merchant_integration_token');

        return response()->json([
            'data' => [
                'merchant_id' => $merchant->id,
                'merchant_name' => $merchant->name,
                'token_name' => $token->name,
                'provider' => $token->provider,
                'permissions' => $token->permissions ?? [],
            ],
        ]);
    }
}
