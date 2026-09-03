<?php

namespace App\Modules\MerchantPortal\Middleware;

use App\Models\Merchant;
use App\Modules\Merchant\Enums\MerchantStatus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantOperational
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = $request->attributes->get('merchant');

        if (! $merchant instanceof Merchant) {
            return $this->forbidden('MERCHANT_ACCESS_UNAVAILABLE', 'Merchant portal access is not available for this account.');
        }

        if ($this->isSafeAccountEndpoint($request)) {
            return in_array($merchant->status, MerchantStatus::registrationValues(), true)
                ? $next($request)
                : $this->forbidden('MERCHANT_ACCESS_UNAVAILABLE', 'Merchant portal access is not available for this account.');
        }

        return match ($merchant->status) {
            MerchantStatus::ACTIVE => $next($request),
            MerchantStatus::PENDING => $this->forbidden('MERCHANT_PENDING_APPROVAL', 'Your merchant account is pending admin approval.'),
            MerchantStatus::REJECTED => $this->forbidden('MERCHANT_REGISTRATION_REJECTED', 'Your merchant registration was rejected.'),
            MerchantStatus::SUSPENDED => $this->forbidden('MERCHANT_SUSPENDED', 'Your merchant account is currently suspended.'),
            default => $this->forbidden('MERCHANT_ACCESS_UNAVAILABLE', 'Merchant portal access is not available for this account.'),
        };
    }

    private function isSafeAccountEndpoint(Request $request): bool
    {
        return $request->is(
            'api/merchant-portal/me',
            'api/merchant-portal/profile',
            'api/merchant-portal/settings',
            'api/merchant-portal/notifications',
            'api/merchant-portal/notifications/*',
        );
    }

    private function forbidden(string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'code' => $code,
            'message' => $message,
        ], 403);
    }
}
