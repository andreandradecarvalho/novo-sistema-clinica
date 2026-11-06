<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['users.view', 'users.manage', 'tenant.manage', 'audit.view', 'clinical.access', 'health_records.view', 'health_records.create', 'patients.view', 'patients.manage', 'patients.review', 'patients.merge', 'companies.view', 'companies.manage', 'units.view', 'units.manage', 'rooms.view', 'rooms.manage', 'professionals.view', 'professionals.manage', 'employments.view', 'employments.manage', 'company_access.view', 'company_access.manage', 'catalog.view', 'catalog.manage', 'consents.view', 'consents.manage', 'programs.view', 'programs.manage', 'protocols.view', 'protocols.manage', 'appointments.confirm', 'visits.view', 'visits.check_in', 'visits.cancel', 'visits.complete', 'executions.view', 'executions.prepare', 'executions.cancel', 'executions.assign_room', 'executions.authorizations.view', 'executions.authorizations.manage', 'executions.perform', 'executions.complete', 'executions.resolve_exception', 'executions.repeat', 'appointments.view', 'appointments.create', 'appointments.cancel', 'appointments.all_units', 'orders.view', 'orders.manage', 'financial_policies.view', 'financial_policies.manage', 'receivables.view', 'receivables.manage', 'receivables.review', 'receivables.settle', 'receivables.cancel', 'protocols.approve', 'company_portal.status.view', 'company_portal.aso.view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
