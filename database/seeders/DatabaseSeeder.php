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
        foreach (['users.view', 'users.manage', 'tenant.manage', 'audit.view', 'clinical.access', 'patients.view', 'patients.manage', 'patients.review', 'patients.merge', 'companies.view', 'companies.manage', 'units.view', 'units.manage', 'professionals.view', 'professionals.manage', 'employments.view', 'employments.manage', 'company_access.view', 'company_access.manage', 'company_portal.status.view', 'company_portal.aso.view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
