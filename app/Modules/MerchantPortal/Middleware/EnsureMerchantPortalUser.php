<?php

namespace App\Modules\MerchantPortal\Middleware;

use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantPortalUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->containsMerchantOverride($request)) {
            $errors = [
                'merchant_id' => [__('merchant_portal.merchant_override_forbidden')],
            ];

            $prohibitedFields = [
                'id',
                'status',
                'pricing_plan_id',
                'wallet_balance',
                'wallet_balances',
                'financial_fields',
                'financial_settings',
                'commission_settings',
                'commission',
                'internal_notes',
                'deleted_at',
                'created_by',
                'updated_by',
            ];

            foreach ($prohibitedFields as $field) {
                if ($request->has($field)) {
                    $attribute = __('validation.attributes.'.$field);

                    if ($attribute === 'validation.attributes.'.$field) {
                        $attribute = str_replace('_', ' ', $field);
                    }

                    $errors[$field] = [__('validation.prohibited', [
                        'attribute' => $attribute,
                    ])];
                }
            }

            return new JsonResponse([
                'message' => __('common.validation_failed'),
                'errors' => $errors,
            ], 422);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        $memberships = MerchantUser::query()
            ->with(['merchant', 'user'])
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();

        if ($memberships->isEmpty()) {
            return $this->forbidden();
        }

        $activeMemberships = $memberships->filter(function (MerchantUser $merchantUser): bool {
            return $merchantUser->status === MerchantPortalUserStatus::ACTIVE
                && $merchantUser->merchant !== null
                && $merchantUser->merchant->deleted_at === null
                && $merchantUser->merchant->status === 'active';
        })->values();

        if ($activeMemberships->count() !== 1) {
            return $this->forbidden();
        }

        $merchantUser = $activeMemberships->first();

        $request->attributes->set('merchant_portal_user', $merchantUser);
        $request->attributes->set('merchant', $merchantUser->merchant);
        $request->attributes->set('merchant_id', $merchantUser->merchant_id);

        return $next($request);
    }

    private function containsMerchantOverride(Request $request): bool
    {
        return $request->query->has('merchant_id') || $request->request->has('merchant_id');
    }

    private function forbidden(): JsonResponse
    {
        return new JsonResponse([
            'message' => __('merchant_portal.access_unavailable'),
        ], 403);
    }
}
