<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Modules\MerchantPortal\Models\MerchantUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class MerchantPortalAccessService
{
    public function __construct(
        private readonly MerchantPortalCapabilityService $merchantPortalCapabilityService,
    ) {
    }

    public function currentMerchantUser(Request $request): MerchantUser
    {
        $merchantUser = $request->attributes->get('merchant_portal_user');

        if (! $merchantUser instanceof MerchantUser) {
            $this->forbid('Merchant portal access is not available for this account.');
        }

        return $merchantUser;
    }

    public function currentMerchant(Request $request): Merchant
    {
        $merchant = $request->attributes->get('merchant');

        if (! $merchant instanceof Merchant) {
            $this->forbid('Merchant portal access is not available for this account.');
        }

        return $merchant;
    }

    public function merchantId(Request $request): int
    {
        return $this->currentMerchant($request)->id;
    }

    public function can(MerchantUser $merchantUser, string $capability): bool
    {
        return in_array($capability, $this->merchantPortalCapabilityService->forRole($merchantUser->role), true);
    }

    public function assertCapability(MerchantUser $merchantUser, string $capability): void
    {
        if (! $this->can($merchantUser, $capability)) {
            $this->forbid('You do not have access to this merchant portal capability.');
        }
    }

    public function assertOwnsMerchantResource(MerchantUser $merchantUser, Model $model): void
    {
        $merchantId = match (true) {
            isset($model->merchant_id) => (int) $model->merchant_id,
            method_exists($model, 'merchant') && $model->merchant !== null => (int) $model->merchant->id,
            default => null,
        };

        if ($merchantId === null || $merchantId !== (int) $merchantUser->merchant_id) {
            $this->forbid('This resource does not belong to the current merchant.');
        }
    }

    private function forbid(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 403));
    }
}
