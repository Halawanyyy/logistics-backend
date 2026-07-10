<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\MerchantPortal\Services\MerchantPortalCapabilityService;
use App\Models\User;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SyncMerchantPortalCapabilitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-portal:sync-capabilities 
                            {--strict : Strict sync mode (unsupported for hardcoded setup, but kept for signature)}
                            {--dry-run : Dry run mode, prints what would change without modifying}
                            {--merchant-id= : Sync only a specific merchant by ID}
                            {--role= : Sync only users of a specific portal role}
                            {--create-missing-owners : Create missing owner portal accounts for active merchants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync capabilities for existing merchant portal users based on the role-to-capability matrix.';

    /**
     * Execute the console command.
     */
    public function handle(MerchantPortalCapabilityService $capabilityService): int
    {
        $dryRun = $this->option('dry-run');
        $merchantId = $this->option('merchant-id');
        $roleOption = $this->option('role');
        $createMissingOwners = $this->option('create-missing-owners');

        $this->info(($dryRun ? '[Dry-run] ' : '') . 'Starting merchant portal capabilities sync...');

        // 1. Fetch memberships to sync
        $query = MerchantUser::query()->whereNull('deleted_at');

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        if ($roleOption) {
            $query->where('role', $roleOption);
        }

        $memberships = $query->get();

        $this->info("Found {$memberships->count()} merchant portal membership records to process.");

        foreach ($memberships as $membership) {
            $user = $membership->user;
            $merchant = $membership->merchant;

            if (!$user || !$merchant) {
                continue;
            }

            // Fetch capabilities that are dynamically returned for this role
            $capabilities = $capabilityService->forRole($membership->role);
            $capabilityCount = count($capabilities);

            $this->line(sprintf(
                'Membership ID: %d | User: %s (%s) | Role: %s | Active capabilities: %d',
                $membership->id,
                $user->name,
                $user->email,
                $membership->role,
                $capabilityCount
            ));
        }

        // 2. Handle missing owner accounts creation if requested
        if ($createMissingOwners) {
            $this->info(($dryRun ? '[Dry-run] ' : '') . 'Checking for active merchants without an owner portal user...');
            
            $activeMerchantsQuery = Merchant::query()->where('status', 'active');
            if ($merchantId) {
                $activeMerchantsQuery->where('id', $merchantId);
            }
            $activeMerchants = $activeMerchantsQuery->get();
            $createdCount = 0;

            foreach ($activeMerchants as $merchant) {
                $hasOwner = MerchantUser::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('role', MerchantPortalRole::OWNER)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$hasOwner) {
                    if (!$merchant->email) {
                        $this->warn("Merchant ID: {$merchant->id} ({$merchant->name}) has no owner account but lacks a valid email. Skipping.");
                        continue;
                    }

                    // Check if User already exists with this email
                    $existingUser = User::query()->where('email', $merchant->email)->first();
                    if ($existingUser) {
                        $this->warn("Merchant ID: {$merchant->id} ({$merchant->name}) has no owner account, but User with email {$merchant->email} already exists. Skipping user creation to prevent duplicate users.");
                        continue;
                    }

                    $this->info("Merchant ID: {$merchant->id} ({$merchant->name}) has no owner account. Creating new owner account...");

                    if (!$dryRun) {
                        $tempPassword = Str::random(12);

                        // Ensure merchant_portal role_id is set
                        $portalRoleObj = Role::query()->where('slug', 'merchant_portal')->first();

                        $newUser = User::create([
                            'name' => $merchant->name . ' Owner',
                            'email' => $merchant->email,
                            'password' => Hash::make($tempPassword),
                            'role' => 'merchant_portal',
                            'role_id' => $portalRoleObj?->id,
                            'email_verified_at' => now(),
                        ]);

                        MerchantUser::create([
                            'merchant_id' => $merchant->id,
                            'user_id' => $newUser->id,
                            'role' => MerchantPortalRole::OWNER,
                            'status' => MerchantPortalUserStatus::ACTIVE,
                            'is_primary' => true,
                            'accepted_at' => now(),
                        ]);

                        $this->info("Successfully created owner account for {$merchant->name}:");
                        $this->line("Email: {$merchant->email}");
                        $this->line("Temporary Password: {$tempPassword}");
                        $createdCount++;
                    } else {
                        $this->line("Would create owner account for {$merchant->name} ({$merchant->email}).");
                        $createdCount++;
                    }
                }
            }

            $this->info(($dryRun ? '[Dry-run] ' : '') . "Created/Identified {$createdCount} missing owner accounts.");
        }

        $this->info('Merchant Portal capabilities sync completed successfully.');

        return 0;
    }
}
