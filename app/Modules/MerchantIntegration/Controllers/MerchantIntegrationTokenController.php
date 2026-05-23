<?php

namespace App\Modules\MerchantIntegration\Controllers;

use App\Models\Merchant;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantIntegration\Resources\MerchantIntegrationTokenResource;
use App\Modules\MerchantIntegration\Services\MerchantIntegrationTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MerchantIntegrationTokenController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MerchantIntegrationTokenService $merchantIntegrationTokenService,
    ) {
    }

    public function index(Merchant $merchant): AnonymousResourceCollection
    {
        $tokens = MerchantIntegrationToken::query()
            ->with(['merchant', 'createdBy', 'revokedBy'])
            ->where('merchant_id', $merchant->id)
            ->latest()
            ->get();

        return MerchantIntegrationTokenResource::collection($tokens);
    }

    public function store(Request $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', Rule::in(MerchantIntegrationToken::providerValues())],
            'permissions' => ['nullable', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(MerchantIntegrationToken::SUPPORTED_PERMISSIONS)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
        ]);

        $result = $this->merchantIntegrationTokenService->generateToken($merchant, $validated, $request->user());
        /** @var MerchantIntegrationToken $token */
        $token = $result['model'];

        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_CREATED,
            'created',
            $token,
            null,
            [
                'user' => $request->user(),
                'metadata' => [
                    'merchant_id' => $merchant->id,
                    'provider' => $token->provider,
                    'token_prefix' => $token->token_prefix,
                    'permissions' => $token->permissions,
                ],
                'notes' => 'Merchant integration token created.',
            ],
        );

        return response()->json([
            'data' => array_merge(
                (new MerchantIntegrationTokenResource($token))->resolve(),
                ['plain_token' => $result['plain_token']]
            ),
        ], 201);
    }

    public function show(MerchantIntegrationToken $token): MerchantIntegrationTokenResource
    {
        return new MerchantIntegrationTokenResource($token->load(['merchant', 'createdBy', 'revokedBy']));
    }

    public function revoke(Request $request, MerchantIntegrationToken $token): MerchantIntegrationTokenResource
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        if ($token->status === MerchantIntegrationToken::STATUS_REVOKED || $token->revoked_at !== null) {
            return new MerchantIntegrationTokenResource($token->load(['merchant', 'createdBy', 'revokedBy']));
        }

        $token = $this->merchantIntegrationTokenService->revokeToken(
            $token,
            $request->user(),
            $validated['notes'] ?? null,
        );

        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_REVOKED,
            'revoked',
            $token,
            null,
            [
                'user' => $request->user(),
                'metadata' => [
                    'merchant_id' => $token->merchant_id,
                    'provider' => $token->provider,
                    'token_prefix' => $token->token_prefix,
                ],
                'notes' => $validated['notes'] ?? 'Merchant integration token revoked.',
            ],
        );

        return new MerchantIntegrationTokenResource($token);
    }

    public function destroy(Request $request, MerchantIntegrationToken $token): JsonResponse
    {
        $this->auditLogService->log(
            AuditEventType::MERCHANT_INTEGRATION_TOKEN_DELETED,
            'deleted',
            $token,
            null,
            [
                'user' => $request->user(),
                'metadata' => [
                    'merchant_id' => $token->merchant_id,
                    'provider' => $token->provider,
                    'token_prefix' => $token->token_prefix,
                ],
                'notes' => 'Merchant integration token deleted.',
            ],
        );

        $token->delete();

        return response()->json([
            'message' => 'Merchant integration token deleted successfully',
        ]);
    }
}
