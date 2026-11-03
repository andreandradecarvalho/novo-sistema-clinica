<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Domain;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Str;

final class Clinics
{
    public static function create(string $email = 'equipe@example.test'): array
    {
        $slug = strtolower((string) Str::ulid());
        $tenant = Tenant::query()->create(['name' => 'Clínica sintética '.$slug, 'slug' => $slug, 'status' => 'active', 'timezone' => 'America/Sao_Paulo']);
        $host = $slug.'.example.test';
        Domain::query()->create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        $password = 'Aa1!'.Str::random(24);
        $user = app(TenantContext::class)->run($tenant, fn () => User::query()->create(['name' => 'Equipe sintética', 'email' => $email, 'password' => $password])->fresh());
        app(TenantContext::class)->run($tenant, fn () => $user->forceFill(['must_change_password' => false])->save());

        return compact('tenant', 'host', 'password', 'user');
    }
}
