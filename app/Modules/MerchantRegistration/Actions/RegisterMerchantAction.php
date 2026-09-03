<?php

namespace App\Modules\MerchantRegistration\Actions;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\Audit\Enums\AuditEventType;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Merchant\Enums\MerchantStatus;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\MerchantPortal\Services\MerchantPortalNotificationService;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterMerchantAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly MerchantPortalNotificationService $notificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Merchant
    {
        return DB::transaction(function () use ($data): Merchant {
            $merchant = Merchant::query()->create([
                'name' => $data['company_name'],
                'company_name' => $data['company_name'],
                'phone' => $data['phone'],
                'email' => strtolower($data['email']),
                'address' => $data['address'] ?? null,
                'governorate_id' => $data['governorate_id'] ?? null,
                'area_id' => $data['area_id'] ?? null,
                'status' => MerchantStatus::PENDING,
                'pricing_plan_id' => null,
                'registration_source' => 'self_registration',
            ]);

            $portalRoleId = Role::query()->where('slug', 'merchant_portal')->value('id');
            $user = User::query()->create([
                'name' => $data['owner_name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'merchant_portal',
                'role_id' => $portalRoleId,
            ]);

            $owner = MerchantUser::query()->create([
                'merchant_id' => $merchant->id,
                'user_id' => $user->id,
                'role' => MerchantPortalRole::OWNER,
                'status' => MerchantPortalUserStatus::ACTIVE,
                'is_primary' => true,
                'accepted_at' => now(),
            ]);

            $this->notificationService->create(
                $merchant->id,
                $owner->id,
                'merchant_registration_submitted',
                'Registration submitted',
                'Your registration has been submitted and is waiting for approval.',
                ['status' => MerchantStatus::PENDING],
            );

            $this->auditLogService->log(
                AuditEventType::MERCHANT_REGISTERED,
                'registered',
                $merchant,
                null,
                [
                    'user_name' => $data['owner_name'],
                    'metadata' => [
                        'merchant_id' => $merchant->id,
                        'company' => $merchant->company_name,
                        'portal_owner_id' => $owner->id,
                        'registration_source' => 'self_registration',
                    ],
                    'notes' => 'Merchant self-registration submitted.',
                ],
            );

            return $merchant->load(['primaryOwner.user', 'governorate', 'area']);
        });
    }
}
