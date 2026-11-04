<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Domain;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final readonly class ProvisionTenant
{
    public function __construct(private TenantContext $context) {}

    public function execute(array $values): Tenant
    {
        try {
            return DB::transaction(function () use ($values): Tenant {
                $tenant = Tenant::query()->firstOrCreate(['slug' => $values['slug']], [
                    'name' => $values['name'], 'status' => 'trial', 'timezone' => 'America/Sao_Paulo',
                ]);
                $domain = Domain::query()->firstOrCreate(['domain' => $values['domain']], ['tenant_id' => $tenant->getKey(), 'is_primary' => true]);
                if ((int) $domain->tenant_id !== (int) $tenant->getKey()) {
                    throw new LogicException('Domain already belongs to another clinic.');
                }
                $this->context->run($tenant, function (): void {
                    app(DatabaseSeeder::class)->run();
                    foreach (['clinic_admin', 'receptionist', 'doctor', 'technician', 'finance', 'company_user'] as $role) {
                        Role::findOrCreate($role, 'web');
                    }
                    Role::findByName('clinic_admin', 'web')->syncPermissions(['users.view', 'users.manage', 'tenant.manage', 'audit.view', 'clinical.access', 'patients.view', 'patients.manage', 'patients.review', 'patients.merge', 'companies.view', 'companies.manage', 'units.view', 'units.manage', 'professionals.view', 'professionals.manage', 'employments.view', 'employments.manage', 'company_access.view', 'company_access.manage']);
                    foreach (['doctor', 'technician'] as $role) {
                        Role::findByName($role, 'web')->syncPermissions(['clinical.access']);
                    }
                });

                return $tenant;
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
