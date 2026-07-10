<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MerchantPortalCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure merchant_portal role exists in the role permission system (internal users role)
        $role = Role::query()->withTrashed()->updateOrCreate(
            ['slug' => 'merchant_portal'],
            [
                'name' => 'Merchant Portal',
                'description' => 'Merchant-facing portal access without internal permissions.',
                'is_system' => true,
                'status' => 'active',
                'deleted_at' => null,
            ]
        );

        // 2. Fetch or create a default active merchant for demo purposes
        $merchant = Merchant::query()->where('status', 'active')->first();
        if ($merchant === null) {
            $merchant = Merchant::create([
                'name' => 'Demo Merchant',
                'company_name' => 'Demo Merchant LLC',
                'phone' => '+201001234500',
                'email' => 'demo.merchant@example.com',
                'address' => 'Cairo, Egypt',
                'status' => 'active',
            ]);
        }

        // 3. Define the demo users mapping
        $demoUsers = [
            [
                'name' => 'Merchant Owner',
                'email' => 'merchant.owner@example.com',
                'portal_role' => MerchantPortalRole::OWNER,
                'is_primary' => true,
            ],
            [
                'name' => 'Merchant Staff',
                'email' => 'merchant.staff@example.com',
                'portal_role' => MerchantPortalRole::STAFF,
                'is_primary' => false,
            ],
            [
                'name' => 'Merchant Accountant',
                'email' => 'merchant.accountant@example.com',
                'portal_role' => MerchantPortalRole::ACCOUNTANT,
                'is_primary' => false,
            ],
            [
                'name' => 'Merchant Warehouse Viewer',
                'email' => 'merchant.warehouse@example.com',
                'portal_role' => MerchantPortalRole::WAREHOUSE_VIEWER,
                'is_primary' => false,
            ],
            [
                'name' => 'Merchant Viewer',
                'email' => 'merchant.viewer@example.com',
                'portal_role' => MerchantPortalRole::VIEWER,
                'is_primary' => false,
            ],
        ];

        // 4. Create users & map them to merchant memberships
        foreach ($demoUsers as $demoUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => Hash::make('secret123'),
                    'role' => 'merchant_portal',
                    'role_id' => $role->id,
                    'email_verified_at' => now(),
                ]
            );

            MerchantUser::query()->withTrashed()->updateOrCreate(
                [
                    'merchant_id' => $merchant->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $demoUser['portal_role'],
                    'status' => MerchantPortalUserStatus::ACTIVE,
                    'is_primary' => $demoUser['is_primary'],
                    'accepted_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }
}
