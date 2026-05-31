<?php

namespace App\Modules\MerchantPortal\Services;

use App\Models\Merchant;
use App\Models\User;
use App\Modules\MerchantPortal\Enums\MerchantPortalRole;
use App\Modules\MerchantPortal\Enums\MerchantPortalUserStatus;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MerchantPortalUserManagementService
{
    public function query(Merchant $merchant, array $filters = []): Builder
    {
        return MerchantUser::query()
            ->with(['merchant', 'user', 'invitedBy'])
            ->where('merchant_id', $merchant->id)
            ->whereNull('deleted_at')
            ->when(isset($filters['role']), fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('id');
    }

    public function create(Merchant $merchant, array $data): MerchantUser
    {
        return DB::transaction(function () use ($merchant, $data) {
            $merchant = $this->lockMerchant($merchant);
            $this->assertMerchantCanReceivePortalUser($merchant);
            $user = $this->resolveUserForCreate($data);
            $existingSameMerchant = MerchantUser::query()
                ->withTrashed()
                ->where('merchant_id', $merchant->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $this->assertMembershipCanBeCreated($merchant, $user, $data, $existingSameMerchant);

            $attributes = $this->membershipAttributes($data);
            $attributes['merchant_id'] = $merchant->id;
            $attributes['user_id'] = $user->id;
            $attributes['is_primary'] = (bool) ($data['is_primary'] ?? false);

            if ($existingSameMerchant !== null) {
                $existingSameMerchant->restore();
                $existingSameMerchant->update($attributes);

                return $existingSameMerchant->fresh()->load(['merchant', 'user', 'invitedBy']);
            }

            return MerchantUser::create($attributes)->load(['merchant', 'user', 'invitedBy']);
        });
    }

    public function update(Merchant $merchant, MerchantUser $merchantUser, array $data): MerchantUser
    {
        return DB::transaction(function () use ($merchant, $merchantUser, $data) {
            $merchant = $this->lockMerchant($merchant);
            $merchantUser = $this->lockMerchantUser($merchant, $merchantUser);
            $this->assertMerchantCanReceivePortalUser($merchant);

            $role = (string) ($data['role'] ?? $merchantUser->role);
            $status = (string) ($data['status'] ?? $merchantUser->status);
            $isPrimary = array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : (bool) $merchantUser->is_primary;

            $this->assertPrimaryOwnerRules(
                $merchant,
                $merchantUser,
                $role,
                $status,
                $isPrimary,
                false,
            );

            if (array_key_exists('name', $data) || array_key_exists('email', $data) || array_key_exists('password', $data)) {
                $this->updateUser($merchantUser->user()->lockForUpdate()->firstOrFail(), $data);
            }

            $merchantUser->update($this->membershipAttributes([
                ...$merchantUser->toArray(),
                ...$data,
                'role' => $role,
                'status' => $status,
                'is_primary' => $isPrimary,
            ]));

            return $merchantUser->fresh()->load(['merchant', 'user', 'invitedBy']);
        });
    }

    public function suspend(Merchant $merchant, MerchantUser $merchantUser): MerchantUser
    {
        return $this->update($merchant, $merchantUser, [
            'status' => MerchantPortalUserStatus::SUSPENDED,
        ]);
    }

    public function activate(Merchant $merchant, MerchantUser $merchantUser): MerchantUser
    {
        return $this->update($merchant, $merchantUser, [
            'status' => MerchantPortalUserStatus::ACTIVE,
            'accepted_at' => $merchantUser->accepted_at ?? now(),
        ]);
    }

    public function delete(Merchant $merchant, MerchantUser $merchantUser): void
    {
        DB::transaction(function () use ($merchant, $merchantUser): void {
            $merchant = $this->lockMerchant($merchant);
            $merchantUser = $this->lockMerchantUser($merchant, $merchantUser);
            $this->assertPrimaryOwnerRules(
                $merchant,
                $merchantUser,
                $merchantUser->role,
                MerchantPortalUserStatus::SUSPENDED,
                false,
                true,
            );
            $merchantUser->delete();
        });
    }

    public function ensureBelongsToMerchant(Merchant $merchant, MerchantUser $merchantUser): MerchantUser
    {
        if ((int) $merchantUser->merchant_id !== (int) $merchant->id || $merchantUser->deleted_at !== null) {
            abort(404);
        }

        return $merchantUser;
    }

    private function lockMerchant(Merchant $merchant): Merchant
    {
        return Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
    }

    private function lockMerchantUser(Merchant $merchant, MerchantUser $merchantUser): MerchantUser
    {
        return MerchantUser::query()
            ->where('merchant_id', $merchant->id)
            ->whereKey($merchantUser->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertMerchantCanReceivePortalUser(Merchant $merchant): void
    {
        if ($merchant->deleted_at !== null || $merchant->status !== 'active') {
            $this->fail('This merchant cannot receive merchant portal users.', 'merchant');
        }
    }

    private function resolveUserForCreate(array $data): User
    {
        if (isset($data['user_id'])) {
            $user = User::query()->whereKey($data['user_id'])->whereNull('deleted_at')->first();

            if ($user === null) {
                $this->fail('The selected user is invalid.', 'user_id');
            }

            return $user;
        }

        $portalRole = Role::query()->where('slug', 'merchant_portal')->first();

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make((string) $data['password']),
            'role' => 'merchant_portal',
            'role_id' => $portalRole?->id,
        ]);
    }

    private function assertMembershipCanBeCreated(
        Merchant $merchant,
        User $user,
        array $data,
        ?MerchantUser $existingSameMerchant,
    ): void {
        $otherMembership = MerchantUser::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->when($existingSameMerchant !== null, fn (Builder $query) => $query->whereKeyNot($existingSameMerchant->id))
            ->lockForUpdate()
            ->first();

        if ($otherMembership !== null) {
            $this->fail('This user is already linked to another active merchant portal membership.', 'user_id');
        }

        if ($existingSameMerchant !== null && $existingSameMerchant->deleted_at === null) {
            $this->fail('This user is already linked to this merchant.', 'user_id');
        }

        $this->assertPrimaryOwnerRules(
            $merchant,
            $existingSameMerchant,
            (string) $data['role'],
            (string) $data['status'],
            (bool) ($data['is_primary'] ?? false),
            false,
        );
    }

    private function assertPrimaryOwnerRules(
        Merchant $merchant,
        ?MerchantUser $merchantUser,
        string $newRole,
        string $newStatus,
        bool $newIsPrimary,
        bool $isDelete,
    ): void {
        if ($newIsPrimary && $newRole !== MerchantPortalRole::OWNER) {
            $this->fail('Primary merchant portal users must have the owner role.', 'is_primary');
        }

        if ($newIsPrimary) {
            $otherPrimaryOwnerExists = MerchantUser::query()
                ->where('merchant_id', $merchant->id)
                ->where('role', MerchantPortalRole::OWNER)
                ->where('status', MerchantPortalUserStatus::ACTIVE)
                ->where('is_primary', true)
                ->whereNull('deleted_at')
                ->when($merchantUser !== null, fn (Builder $query) => $query->whereKeyNot($merchantUser->id))
                ->lockForUpdate()
                ->exists();

            if ($otherPrimaryOwnerExists) {
                $this->fail('A merchant cannot have more than one active primary owner.', 'is_primary');
            }
        }

        if ($merchantUser === null) {
            return;
        }

        $currentlyActiveOwner = $merchantUser->role === MerchantPortalRole::OWNER
            && $merchantUser->status === MerchantPortalUserStatus::ACTIVE
            && $merchantUser->deleted_at === null;

        $willRemainActiveOwner = ! $isDelete
            && $newRole === MerchantPortalRole::OWNER
            && $newStatus === MerchantPortalUserStatus::ACTIVE;

        if (! $currentlyActiveOwner || $willRemainActiveOwner) {
            return;
        }

        $otherActiveOwnerExists = MerchantUser::query()
            ->where('merchant_id', $merchant->id)
            ->where('role', MerchantPortalRole::OWNER)
            ->where('status', MerchantPortalUserStatus::ACTIVE)
            ->whereNull('deleted_at')
            ->whereKeyNot($merchantUser->id)
            ->lockForUpdate()
            ->exists();

        if (! $otherActiveOwnerExists) {
            $this->fail('You cannot remove, suspend, or demote the only active owner for this merchant.', 'role');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function membershipAttributes(array $data): array
    {
        $attributes = Arr::only($data, [
            'role',
            'status',
            'invited_by',
            'invited_at',
            'accepted_at',
            'last_login_at',
        ]);

        $attributes['is_primary'] = (bool) ($data['is_primary'] ?? false);

        if (! array_key_exists('invited_at', $attributes) && ($attributes['status'] ?? null) === MerchantPortalUserStatus::INVITED) {
            $attributes['invited_at'] = now();
        }

        if (! array_key_exists('accepted_at', $attributes) && ($attributes['status'] ?? null) === MerchantPortalUserStatus::ACTIVE) {
            $attributes['accepted_at'] = now();
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateUser(User $user, array $data): void
    {
        $attributes = Arr::only($data, ['name', 'email']);

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $attributes['password'] = Hash::make((string) $data['password']);
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }
    }

    private function fail(string $message, string $field): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                $field => [$message],
            ],
        ], 422));
    }
}
