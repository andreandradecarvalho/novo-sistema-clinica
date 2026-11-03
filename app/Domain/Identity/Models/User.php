<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LogicException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements MustVerifyEmailContract
{
    use BelongsToTenant, MustVerifyEmail, Notifiable, SoftDeletes, TwoFactorAuthenticatable;
    use HasRoles { getStoredRole as private resolveStoredRole;
        hasPermissionTo as private resolveHasPermissionTo;
        getStoredPermission as private resolveStoredPermission; }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            return false;
        }

        return $this->resolveHasPermissionTo($permission, $guardName);
    }

    protected function getStoredPermission($permissions)
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant permission mutation is forbidden.');
        }

        return $this->resolveStoredPermission($permissions);
    }

    protected function getStoredRole($role): RoleContract
    {
        $id = app(TenantContext::class)->id();
        $role = $this->resolveStoredRole($role);
        if ((int) $this->tenant_id !== $id || (int) $role->tenant_id !== $id) {
            throw new LogicException('Cross-tenant role assignment is forbidden.');
        }

        return $role;
    }

    #[\Override]
    protected $fillable = ['name', 'email', 'password'];

    #[\Override]
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'cpf_encrypted', 'cpf_hash'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean', 'must_change_password' => 'boolean', 'two_factor_confirmed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (User $user): void {
            $user->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function requiresTwoFactor(): bool
    {
        return $this->can('clinical.access');
    }
}
