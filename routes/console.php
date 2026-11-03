<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateTenantAdministrator;
use App\Domain\Tenancy\Actions\ProvisionTenant;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('tenant:create {name} {--slug=} {--domain=}', function (): int {
    $values = Validator::make(['name' => $this->argument('name'), 'slug' => $this->option('slug'), 'domain' => strtolower((string) $this->option('domain'))], [
        'name' => ['required', 'string', 'max:180'],
        'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:120'],
        'domain' => ['required', 'regex:/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', 'max:190'],
    ])->validate();
    app(ProvisionTenant::class)->execute($values);
    $this->info('Clínica provisionada; nenhum banco adicional foi criado.');

    return 0;
})->purpose('Provisionar clínica e papéis no banco compartilhado, sem credenciais fixas.');

Artisan::command('tenant:admin {slug} {email} {name}', function (): int {
    $tenant = Tenant::query()->where('slug', $this->argument('slug'))->firstOrFail();
    $password = $this->secret('Senha inicial (não será exibida)');
    $values = Validator::make(['email' => strtolower(trim((string) $this->argument('email'))), 'name' => $this->argument('name'), 'password' => $password], [
        'email' => ['required', 'email', 'max:180'], 'name' => ['required', 'string', 'max:180'], 'password' => ['required', Password::defaults()],
    ])->validate();
    app(CreateTenantAdministrator::class)->execute($tenant, $values);
    $this->info('Administrador criado. Configure o segundo fator antes de acessar a clínica.');

    return 0;
})->purpose('Criar administrador nominal por entrada interativa, sem senha em argumentos.');
