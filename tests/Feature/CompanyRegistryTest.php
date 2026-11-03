<?php

declare(strict_types=1);

use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Actions\SaveCompanyUnit;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Shared\ValueObjects\Cnpj;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Clinics;

beforeEach(function (): void {
    $this->withoutVite();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    DB::beginTransaction();
    $this->seed();
    $this->context = app(TenantContext::class);
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['companies.view', 'companies.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    $this->companyA = $this->context->run($this->a['tenant'], fn () => app(SaveCompany::class)->execute(['name' => 'Empresa sintética A', 'is_active' => true]));
    $this->companyB = $this->context->run($this->b['tenant'], fn () => app(SaveCompany::class)->execute(['name' => 'Empresa sintética B', 'is_active' => true]));
    $this->base = 'http://'.$this->a['host'];
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('normalizes the official alphanumeric CNPJ example and rejects malformed identifiers', function (): void {
    expect(new Cnpj('12.abc.345/01de-35')->value)->toBe('12ABC34501DE35');
    expect(new Cnpj('12.345.678/0001-95')->value)->toBe('12345678000195');
    foreach (['12ABC34501DE36', '00000000000000', '12.ABC.345/01DE-AA', '12ABC34501DÉ35', '12-ABC-345-01DE-35'] as $value) {
        expect(fn () => new Cnpj($value))->toThrow(InvalidArgumentException::class);
    }
});

it('creates and edits a company without changing its stable identifier and records only metadata', function (): void {
    $this->post($this->base.'/companies', ['name' => 'Empresa exemplo', 'cnpj' => '12.abc.345/01de-35', 'is_active' => 1])->assertRedirect();
    $company = $this->context->run($this->a['tenant'], fn () => Company::query()->where('cnpj', '12ABC34501DE35')->sole());
    $slug = $company->slug;
    $this->put($this->base.'/companies/'.$company->public_id, ['name' => 'Empresa corrigida', 'is_active' => 0])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($company, $slug): void {
        expect($company->fresh()->name)->toBe('Empresa corrigida');
        expect($company->fresh()->is_active)->toBeFalse();
        expect($company->fresh()->slug)->toBe($slug);
        expect($company->fresh()->cnpj)->toBe('12ABC34501DE35');
    });
    $this->get($this->base.'/companies/'.$company->public_id)->assertOk()->assertSee('Empresa corrigida');
    $events = DB::table('activity_log')->where('subject_id', $company->id)->where('log_name', 'companies')->get();
    expect($events->pluck('event')->all())->toBe(['company_created', 'company_updated', 'company_viewed']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
});

it('enforces normalized CNPJ uniqueness only within the current clinic', function (): void {
    $input = ['name' => 'Empresa exemplo', 'cnpj' => '12ABC34501DE35', 'is_active' => 1];
    $this->post($this->base.'/companies', $input)->assertRedirect();
    $this->postJson($this->base.'/companies', ['cnpj' => '12.abc.345/01de-35'] + $input)->assertUnprocessable()->assertJsonValidationErrors('cnpj');
    $this->postJson($this->base.'/logout')->assertSuccessful();
    $baseB = 'http://'.$this->b['host'];
    $this->postJson($baseB.'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
    $this->post($baseB.'/companies', $input)->assertRedirect();
    expect(DB::table('companies')->where('cnpj', '12ABC34501DE35')->count())->toBe(2);
});

it('creates establishments under their route company and allows editing and inactivation', function (): void {
    $url = $this->base.'/companies/'.$this->companyA->public_id.'/units';
    $this->get($url.'/create')->assertOk()->assertSee('Novo estabelecimento');
    $this->post($url, ['name' => 'Estabelecimento sintético', 'city' => 'Cidade teste', 'state' => 'sp', 'is_active' => 1])->assertRedirect();
    $unit = $this->context->run($this->a['tenant'], fn () => CompanyUnit::query()->sole());
    expect((int) $unit->company_id)->toBe((int) $this->companyA->id);
    expect($unit->state)->toBe('SP');
    $this->get($url.'/'.$unit->id.'/edit')->assertOk()->assertSee('Estabelecimento sintético');
    $this->put($url.'/'.$unit->id, ['name' => 'Unidade corrigida', 'city' => null, 'state' => null, 'is_active' => 0])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect($unit->fresh()->is_active)->toBeFalse());
    $this->get($this->base.'/companies/'.$this->companyA->public_id)->assertOk()->assertSee('Unidade corrigida');
});

it('rejects establishment access under a different company even within the same clinic', function (): void {
    [$other, $unit] = $this->context->run($this->a['tenant'], function (): array {
        $other = app(SaveCompany::class)->execute(['name' => 'Outra empresa A', 'is_active' => true]);
        $unit = app(SaveCompanyUnit::class)->execute($this->companyA, ['name' => 'Unidade A', 'is_active' => true]);

        return [$other, $unit];
    });
    $wrongUrl = $this->base.'/companies/'.$other->public_id.'/units/'.$unit->id;
    $this->get($wrongUrl.'/edit')->assertNotFound();
    $this->putJson($wrongUrl, ['name' => 'Inválido', 'is_active' => 1])->assertNotFound();
    $this->context->run($this->a['tenant'], function () use ($other, $unit): void {
        expect(fn () => app(SaveCompanyUnit::class)->execute($other, ['name' => 'Inválido'], $unit))->toThrow(LogicException::class);
        expect(fn () => $unit->forceFill(['company_id' => $other->id])->save())->toThrow(LogicException::class);
    });
});

it('rejects cross clinic bindings ownership injection and foreign parent references', function (): void {
    $foreignUnit = $this->context->run($this->b['tenant'], fn () => app(SaveCompanyUnit::class)->execute($this->companyB, ['name' => 'Estabelecimento B', 'is_active' => true]));
    $this->get($this->base.'/companies/'.$this->companyA->public_id.'/units/'.$foreignUnit->id.'/edit')->assertNotFound();
    $this->get($this->base.'/companies/'.$this->companyA->public_id)->assertOk()->assertDontSee('Estabelecimento B');
    $url = $this->base.'/companies/'.$this->companyB->public_id;
    $this->get($url)->assertNotFound();
    $this->putJson($url, ['name' => 'Inválido', 'is_active' => 1])->assertNotFound();
    $this->get($url.'/units/create')->assertNotFound();
    $this->postJson($this->base.'/companies', ['name' => 'Inválido', 'is_active' => 1, 'tenant_id' => $this->b['tenant']->id])->assertUnprocessable();
    $this->postJson($this->base.'/companies/'.$this->companyA->public_id.'/units', ['name' => 'Inválido', 'is_active' => 1, 'company_id' => $this->companyB->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(SaveCompanyUnit::class)->execute($this->companyB, ['name' => 'Inválido', 'is_active' => 1]))->toThrow(ModelNotFoundException::class);
        expect(fn () => DB::table('company_units')->insert(['tenant_id' => $this->a['tenant']->id, 'company_id' => $this->companyB->id, 'name' => 'Inválido']))->toThrow(QueryException::class);
    });
    expect(fn () => Company::query()->count())->toThrow(MissingTenant::class);
    expect(fn () => CompanyUnit::query()->count())->toThrow(MissingTenant::class);
});

it('denies company mutations to a viewer and all access without permission or second factor', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.manage'));
    $this->get($this->base.'/companies')->assertOk()->assertSee('Empresa sintética A')->assertDontSee('Empresa sintética B');
    $this->postJson($this->base.'/companies', ['name' => 'Inválido', 'is_active' => 1])->assertForbidden();
    $url = $this->base.'/companies/'.$this->companyA->public_id;
    $this->postJson($url.'/units', ['name' => 'Inválido', 'is_active' => 1])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.view'));
    $this->get($url)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('companies.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base.'/companies')->assertForbidden();
});

it('renders forms and validates CNPJ UF and status without flashing identifiers', function (): void {
    $this->post($this->base.'/companies', ['name' => 'Inválido', 'is_active' => ['invalid']])->assertSessionHasErrors('is_active');
    $this->get($this->base.'/companies/create')->assertOk()->assertSee('Nova empresa');
    $this->get($this->base.'/companies/'.$this->companyA->public_id.'/edit')->assertOk()->assertSee('Editar empresa');
    $this->post($this->base.'/companies', ['name' => 'Inválido', 'cnpj' => '123', 'is_active' => 1])->assertSessionHasErrors('cnpj')->assertSessionMissing('_old_input.cnpj');
    $this->postJson($this->base.'/companies/'.$this->companyA->public_id.'/units', ['name' => 'Inválido', 'state' => 'ZZ', 'is_active' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors(['state', 'is_active']);
});
