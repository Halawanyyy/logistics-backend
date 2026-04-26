<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Modules\MerchantPortal\Models\MerchantUser;
use App\Modules\RolePermission\Models\Permission;
use App\Modules\RolePermission\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // 'deleted_at' => 'datetime',
        ];
    }

    public function roleRelation(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function merchantUsers(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    public function activeMerchantUser(): HasOne
    {
        return $this->hasOne(MerchantUser::class)
            ->whereNull('deleted_at')
            ->where('status', 'active');
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->resolvedRole();

        if ($role === null || $role->status !== 'active') {
            return false;
        }

        if ($role->slug === 'super_admin') {
            return true;
        }

        return $this->resolvedPermissions()->contains('slug', $permission);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->resolvedRole()?->slug === 'super_admin';
    }

    private function resolvedRole(): ?Role
    {
        if ($this->relationLoaded('roleRelation')) {
            return $this->roleRelation;
        }

        if ($this->role_id !== null) {
            return $this->roleRelation()->with('permissions')->first();
        }

        if (filled($this->role)) {
            return Role::query()
                ->with('permissions')
                ->where('slug', $this->role)
                ->first();
        }

        return null;
    }

    /**
     * @return Collection<int, Permission>
     */
    private function resolvedPermissions(): Collection
    {
        $role = $this->resolvedRole();

        if ($role === null) {
            return collect();
        }

        if ($role->relationLoaded('permissions')) {
            return $role->permissions;
        }

        return $role->permissions()->get();
    }
}
