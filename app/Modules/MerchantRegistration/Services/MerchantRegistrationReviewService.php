<?php

namespace App\Modules\MerchantRegistration\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Merchant\Enums\MerchantStatus;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Services\MerchantPortalNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class MerchantRegistrationReviewService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MerchantPortalNotificationService $notificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return Merchant::query()
            ->with(['primaryOwner.user', 'pricingPlan', 'reviewer'])
            ->where('registration_source', 'self_registration')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! isset($filters['status']), fn (Builder $query) => $query->where('status', MerchantStatus::PENDING))
            ->when(isset($filters['company_name']), fn (Builder $query) => $query->where('company_name', 'like', '%'.$filters['company_name'].'%'))
            ->when(isset($filters['phone']), fn (Builder $query) => $query->where('phone', 'like', '%'.$filters['phone'].'%'))
            ->when(isset($filters['owner_email']), function (Builder $query) use ($filters): void {
                $query->whereHas('primaryOwner.user', fn (Builder $ownerQuery) => $ownerQuery->where('email', 'like', '%'.$filters['owner_email'].'%'));
            })
            ->when(isset($filters['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('company_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('primaryOwner.user', function (Builder $ownerQuery) use ($search): void {
                            $ownerQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->latest('created_at');
    }

    public function find(Merchant $merchant): Merchant
    {
        $this->assertSelfRegistration($merchant);

        return $merchant->load(['primaryOwner.user', 'pricingPlan', 'reviewer', 'governorate', 'area']);
    }

    public function approve(Merchant $merchant, User $reviewer, ?int $pricingPlanId, ?string $notes): Merchant
    {
        return DB::transaction(function () use ($merchant, $reviewer, $pricingPlanId, $notes): Merchant {
            $merchant = $this->lockPending($merchant);
            $this->assertReviewerIsNotMerchant($merchant, $reviewer);
            $reviewedAt = now();

            $merchant->update([
                'status' => MerchantStatus::ACTIVE,
                'pricing_plan_id' => $pricingPlanId,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'approval_notes' => $notes,
                'rejection_reason' => null,
            ]);

            $owner = $merchant->primaryOwner()->lockForUpdate()->firstOrFail();
            $owner->update([
                'status' => MerchantPortalUserStatus::ACTIVE,
                'accepted_at' => $owner->accepted_at ?? $reviewedAt,
            ]);

            $this->notificationService->create(
                $merchant->id,
                $owner->id,
                'merchant_registration_approved',
                'Merchant registration approved',
                'Your merchant account has been approved. You can now use the platform.',
                ['status' => MerchantStatus::ACTIVE, 'reviewed_at' => $reviewedAt->toISOString()],
            );

            $this->auditLogService->log(
                AuditEventType::MERCHANT_REGISTRATION_APPROVED,
                'approved',
                $merchant,
                null,
                [
                    'user' => $reviewer,
                    'old_values' => ['status' => MerchantStatus::PENDING],
                    'new_values' => ['status' => MerchantStatus::ACTIVE, 'pricing_plan_id' => $pricingPlanId],
                    'metadata' => [
                        'merchant_id' => $merchant->id,
                        'approved_by' => $reviewer->id,
                        'approved_at' => $reviewedAt->toISOString(),
                        'pricing_plan_id' => $pricingPlanId,
                    ],
                    'notes' => $notes ?? 'Merchant registration approved.',
                ],
            );

            return $this->find($merchant->fresh());
        });
    }

    public function reject(Merchant $merchant, User $reviewer, string $reason): Merchant
    {
        return DB::transaction(function () use ($merchant, $reviewer, $reason): Merchant {
            $merchant = $this->lockPending($merchant);
            $this->assertReviewerIsNotMerchant($merchant, $reviewer);
            $reviewedAt = now();

            $merchant->update([
                'status' => MerchantStatus::REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => $reason,
                'approval_notes' => null,
            ]);

            $owner = $merchant->primaryOwner()->lockForUpdate()->firstOrFail();

            $this->notificationService->create(
                $merchant->id,
                $owner->id,
                'merchant_registration_rejected',
                'Merchant registration rejected',
                'Your merchant registration was rejected. '.$reason,
                ['status' => MerchantStatus::REJECTED, 'reason' => $reason, 'reviewed_at' => $reviewedAt->toISOString()],
            );

            $this->auditLogService->log(
                AuditEventType::MERCHANT_REGISTRATION_REJECTED,
                'rejected',
                $merchant,
                null,
                [
                    'user' => $reviewer,
                    'old_values' => ['status' => MerchantStatus::PENDING],
                    'new_values' => ['status' => MerchantStatus::REJECTED, 'rejection_reason' => $reason],
                    'metadata' => [
                        'merchant_id' => $merchant->id,
                        'rejected_by' => $reviewer->id,
                        'rejected_at' => $reviewedAt->toISOString(),
                        'reason' => $reason,
                    ],
                    'notes' => 'Merchant registration rejected.',
                ],
            );

            return $this->find($merchant->fresh());
        });
    }

    private function lockPending(Merchant $merchant): Merchant
    {
        $locked = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
        $this->assertSelfRegistration($locked);

        if ($locked->status !== MerchantStatus::PENDING) {
            throw new HttpResponseException(response()->json([
                'code' => 'INVALID_MERCHANT_REGISTRATION_TRANSITION',
                'message' => 'Only pending merchant registrations can be reviewed.',
            ], 409));
        }

        return $locked;
    }

    private function assertReviewerIsNotMerchant(Merchant $merchant, User $reviewer): void
    {
        if ($merchant->merchantUsers()->where('user_id', $reviewer->id)->whereNull('deleted_at')->exists()) {
            throw new HttpResponseException(response()->json([
                'code' => 'MERCHANT_SELF_REVIEW_FORBIDDEN',
                'message' => 'A merchant portal user cannot review their own merchant registration.',
            ], 403));
        }
    }

    private function assertSelfRegistration(Merchant $merchant): void
    {
        if ($merchant->registration_source !== 'self_registration') {
            abort(404);
        }
    }
}
