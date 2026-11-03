<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Tenant;
use Closure;
use Spatie\Permission\PermissionRegistrar;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new MissingTenant;
    }

    public function id(): int
    {
        return (int) $this->require()->getKey();
    }

    public function set(?Tenant $tenant): void
    {
        $user = auth()->guard()->getUser();
        if ($user instanceof User) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
        $registrar = app(PermissionRegistrar::class);
        $registrar->clearPermissionsCollection();
        $this->tenant = $tenant;
        $registrar->setPermissionsTeamId($tenant?->getKey());
        auth()->forgetGuards();
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);
        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    public function key(string $key): string
    {
        return 'tenant:'.$this->id().':'.$key;
    }
}
