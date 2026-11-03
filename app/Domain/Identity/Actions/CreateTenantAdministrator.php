<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class CreateTenantAdministrator
{
    public function __construct(private TenantContext $context) {}

    public function execute(Tenant $tenant, array $values): User
    {
        return $this->context->run($tenant, fn () => DB::transaction(function () use ($values): User {
            $user = User::query()->create($values);
            $user->assignRole('clinic_admin');

            return $user;
        }));
    }
}
