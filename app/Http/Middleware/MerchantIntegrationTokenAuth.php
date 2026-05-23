<?php

namespace App\Http\Middleware;

use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\MerchantIntegration\Models\MerchantIntegrationToken;
use App\Modules\MerchantIntegration\Services\MerchantIntegrationTokenService;
use App\Modules\Settings\Services\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MerchantIntegrationTokenAuth
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MerchantIntegrationTokenService $merchantIntegrationTokenService,
        private readonly SystemSettingService $systemSettingService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->systemSettingService->get('integration_tokens_enabled', true)) {
            return response()->json([
                'message' => 'Integration tokens are disabled.',
            ], 403);
        }

        $plainToken = $this->extractToken($request);

        if ($plainToken === null) {
            return response()->json([
                'message' => 'Invalid integration token.',
            ], 401);
        }

        $token = $this->merchantIntegrationTokenService->verifyToken($plainToken);

        if ($token === null) {
            $candidate = $this->merchantIntegrationTokenService->findByPlainToken($plainToken);

            if ($candidate?->status === MerchantIntegrationToken::STATUS_REVOKED || $candidate?->revoked_at !== null) {
                return response()->json([
                    'message' => 'Integration token revoked.',
                ], 401);
            }

            if ($candidate?->isExpired()) {
                return response()->json([
                    'message' => 'Integration token expired.',
                ], 401);
            }

            return response()->json([
                'message' => 'Invalid integration token.',
            ], 401);
        }

        $request->attributes->set('merchant', $token->merchant);
        $request->attributes->set('merchant_integration_token', $token);

        $this->auditLogService->log(
            AuditEventType::INTEGRATION_TOKEN_USED,
            'used',
            $token,
            null,
            [
                'metadata' => [
                    'merchant_id' => $token->merchant_id,
                    'provider' => $token->provider,
                ],
                'notes' => 'Integration token used.',
            ],
        );

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->bearerToken();

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $altHeader = $request->header('X-Merchant-Token');

        return is_string($altHeader) && $altHeader !== ''
            ? $altHeader
            : null;
    }
}
