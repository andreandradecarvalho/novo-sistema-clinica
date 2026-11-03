<?php

declare(strict_types=1);

use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Actions\SaveCompanyUnit;
use App\Domain\Occupational\Actions\CreateEmployment;
use App\Domain\Occupational\Actions\EndEmployment;
use App\Domain\Occupational\Actions\VersionEmployment;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Actions\SavePatient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $clinic['user']->givePermissionTo(['patients.view', 'patients.manage', 'companies.view', 'employments.view', 'employments.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    [$this->patient, $this->company, $this->unit] = $this->context->run($this->a['tenant'], function (): array {
        $patient = app(SavePatient::class)->execute(['name' => 'Trabalhador sintético']);
        $company = app(SaveCompany::class)->execute(['name' => 'Empregador sintético A', 'is_active' => true]);
        $unit = app(SaveCompanyUnit::class)->execute($company, ['name' => 'Estabelecimento A', 'is_active' => true]);

        return [$patient, $company, $unit];
    });
    [$this->patientB, $this->companyB] = $this->context->run($this->b['tenant'], fn () => [
        app(SavePatient::class)->execute(['name' => 'Trabalhador B']),
        app(SaveCompany::class)->execute(['name' => 'Empregador sintético B', 'is_active' => true]),
    ]);
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->patient->public_id.'/employments';
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates parallel employments without replacing patient summary fields or granting employer access', function (): void {
    $input = ['company_unit_id' => $this->unit->id, 'starts_on' => '2026-01-01', 'job_title' => 'Função sintética', 'department' => 'Setor sintético', 'registration_number' => 'SINT-01'];
    $this->get($this->base.'/create')->assertOk()->assertSee('Empregador sintético A')->assertDontSee('Empregador sintético B');
    $this->get($this->base.'/register/'.$this->company->public_id)->assertOk()->assertSee('Estabelecimento A');
    $this->post($this->base.'/register/'.$this->company->public_id, $input)->assertRedirect();
    $this->post($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-02-01'])->assertRedirect();
    $this->get($this->base)->assertOk()->assertSee('Função sintética')->assertSee('SINT-01');
    $this->context->run($this->a['tenant'], function (): void {
        expect($this->patient->employments()->count())->toBe(2);
        expect($this->patient->fresh()->company_id)->toBeNull();
        expect($this->a['user']->roles)->toHaveCount(0);
    });
    $audit = DB::table('activity_log')->where('log_name', 'employments')->get();
    expect($audit->pluck('properties')->filter())->toBeEmpty();
});

it('versions function and establishment atomically while preserving the previous context', function (): void {
    $old = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01', 'company_unit_id' => $this->unit->id, 'job_title' => 'Função anterior', 'department' => 'Setor original']));
    $nextUnit = $this->context->run($this->a['tenant'], fn () => app(SaveCompanyUnit::class)->execute($this->company, ['name' => 'Novo estabelecimento', 'is_active' => true]));
    $this->get($this->base.'/'.$old->id.'/version')->assertOk()->assertSee('Nova versão de vínculo');
    $this->post($this->base.'/'.$old->id.'/version', ['starts_on' => '2026-03-01', 'job_title' => 'Nova função', 'company_unit_id' => $nextUnit->id])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($old, $nextUnit): void {
        $previous = $old->fresh();
        expect($previous->job_title)->toBe('Função anterior');
        expect((int) $previous->company_unit_id)->toBe((int) $this->unit->id);
        expect($previous->ends_on->format('Y-m-d'))->toBe('2026-02-28');
        expect($previous->termination_date)->toBeNull();
        $next = EmploymentLink::query()->whereNull('ends_on')->sole();
        expect($next->job_title)->toBe('Nova função');
        expect($next->department)->toBe('Setor original');
        expect((int) $next->company_unit_id)->toBe((int) $nextUnit->id);
        expect(fn () => app(VersionEmployment::class)->execute($this->patient, $old, ['starts_on' => '2026-04-01']))->toThrow(ValidationException::class);
        expect(EmploymentLink::query()->count())->toBe(2);
    });
    $event = DB::table('activity_log')->where('event', 'employment_version_created')->sole();
    expect(json_decode($event->properties, true))->toBe(['previous_link_id' => $old->id]);
});

it('registers termination once without overwriting historical job data', function (): void {
    $link = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01', 'job_title' => 'Função preservada']));
    $this->postJson($this->base.'/'.$link->id.'/terminate', ['termination_date' => '2025-12-31'])->assertUnprocessable();
    $this->post($this->base.'/'.$link->id.'/terminate', ['termination_date' => '2026-06-30'])->assertRedirect();
    $this->postJson($this->base.'/'.$link->id.'/terminate', ['termination_date' => '2026-07-01'])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function () use ($link): void {
        $link = $link->fresh();
        expect($link->ends_on->format('Y-m-d'))->toBe('2026-06-30');
        expect($link->termination_date->format('Y-m-d'))->toBe('2026-06-30');
        expect($link->job_title)->toBe('Função preservada');
        expect(fn () => $link->forceFill(['job_title' => 'Inválido'])->save())->toThrow(LogicException::class);
        expect(fn () => $link->delete())->toThrow(LogicException::class);
    });
});

it('rejects an establishment belonging to a different company in the same clinic', function (): void {
    $wrong = $this->context->run($this->a['tenant'], function () {
        $company = app(SaveCompany::class)->execute(['name' => 'Outro empregador', 'is_active' => true]);

        return app(SaveCompanyUnit::class)->execute($company, ['name' => 'Estabelecimento de outra empresa', 'is_active' => true]);
    });
    $this->postJson($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-01-01', 'company_unit_id' => $wrong->id])->assertUnprocessable()->assertJsonValidationErrors('company_unit_id');
    expect(DB::table('patient_company_links')->count())->toBe(0);
});

it('rejects foreign patients companies links and parent injection through HTTP and database FKs', function (): void {
    $foreign = $this->context->run($this->b['tenant'], fn () => app(CreateEmployment::class)->execute($this->patientB, $this->companyB, ['starts_on' => '2026-01-01']));
    $this->get('http://'.$this->a['host'].'/patients/'.$this->patientB->public_id.'/employments')->assertNotFound();
    $this->get($this->base.'/register/'.$this->companyB->public_id)->assertNotFound();
    $this->postJson($this->base.'/register/'.$this->companyB->public_id, ['starts_on' => '2026-01-01'])->assertNotFound();
    $this->postJson($this->base.'/'.$foreign->id.'/terminate', ['termination_date' => '2026-02-01'])->assertNotFound();
    $this->postJson($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-01-01', 'patient_id' => $this->patientB->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(CreateEmployment::class)->execute($this->patientB, $this->company, ['starts_on' => '2026-01-01']))->toThrow(ModelNotFoundException::class);
        expect(fn () => DB::table('patient_company_links')->insert(['tenant_id' => $this->a['tenant']->id, 'patient_id' => $this->patient->id, 'company_id' => $this->companyB->id]))->toThrow(QueryException::class);
    });
    expect(fn () => EmploymentLink::query()->count())->toThrow(MissingTenant::class);
});

it('binds each link to its exact patient even within the same clinic', function (): void {
    [$other, $link] = $this->context->run($this->a['tenant'], function (): array {
        $other = app(SavePatient::class)->execute(['name' => 'Outro trabalhador A']);
        $link = app(CreateEmployment::class)->execute($other, $this->company, ['starts_on' => '2026-01-01']);

        return [$other, $link];
    });
    $this->get($this->base.'/'.$link->id.'/version')->assertNotFound();
    $this->postJson($this->base.'/'.$link->id.'/version', ['starts_on' => '2026-02-01'])->assertNotFound();
    $this->context->run($this->a['tenant'], function () use ($link): void {
        expect(fn () => app(EndEmployment::class)->execute($this->patient, $link, '2026-02-01'))->toThrow(ModelNotFoundException::class);
    });
});

it('refuses invalid version dates and rolls back without closing the previous record', function (): void {
    $link = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01', 'company_unit_id' => $this->unit->id]));
    $this->postJson($this->base.'/'.$link->id.'/version', ['starts_on' => '2026-01-01'])->assertUnprocessable();
    $this->postJson($this->base.'/'.$link->id.'/version', ['starts_on' => '2026-02-01', 'company_unit_id' => 99999999])->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => expect($link->fresh()->ends_on)->toBeNull());
    expect(DB::table('patient_company_links')->count())->toBe(1);
});

it('requires both patient and employment permissions and keeps personal fields out of flash', function (): void {
    $this->post($this->base.'/register/'.$this->company->public_id, ['job_title' => 'Função sintética', 'registration_number' => 'SINT', 'department' => 'Setor'])
        ->assertSessionHasErrors('starts_on')->assertSessionMissing('_old_input.job_title')->assertSessionMissing('_old_input.registration_number')->assertSessionMissing('_old_input.department');
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('employments.manage'));
    $this->get($this->base)->assertOk();
    $this->postJson($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-01-01'])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.view'));
    $this->get($this->base)->assertForbidden();
});

it('rejects inactive employers and establishments while keeping historical links readable and terminable', function (): void {
    $link = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01']));
    $this->context->run($this->a['tenant'], fn () => $this->unit->update(['is_active' => false]));
    $this->postJson($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-02-01', 'company_unit_id' => $this->unit->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => $this->company->update(['is_active' => false]));
    $this->get($this->base)->assertOk()->assertSee('Empregador sintético A');
    $this->get($this->base.'/register/'.$this->company->public_id)->assertNotFound();
    $this->postJson($this->base.'/register/'.$this->company->public_id, ['starts_on' => '2026-02-01'])->assertUnprocessable();
    $this->post($this->base.'/'.$link->id.'/terminate', ['termination_date' => '2026-06-30'])->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => DB::table('patient_company_links')->insert(['tenant_id' => $this->a['tenant']->id, 'patient_id' => $this->patient->id, 'company_id' => $this->company->id, 'starts_on' => '2026-02-01', 'ends_on' => '2026-01-01']))->toThrow(QueryException::class);
    });
});

it('rolls back both versions if audit persistence fails after writing the records', function (): void {
    $link = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01', 'job_title' => 'Função original']));
    $this->app->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit persistence failure.');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function () use ($link): void {
            expect(fn () => app(VersionEmployment::class)->execute($this->patient, $link, ['starts_on' => '2026-02-01', 'job_title' => 'Nova função']))->toThrow(RuntimeException::class);
            expect($link->fresh()->ends_on)->toBeNull();
            expect($link->fresh()->job_title)->toBe('Função original');
            expect(EmploymentLink::query()->count())->toBe(1);
        });
    } finally {
        $this->app->forgetInstance(RegistryAudit::class);
    }
});

it('encrypts historical employer labels and preserves them after the registry is renamed', function (): void {
    $link = $this->context->run($this->a['tenant'], fn () => app(CreateEmployment::class)->execute($this->patient, $this->company, ['starts_on' => '2026-01-01', 'company_unit_id' => $this->unit->id]));
    $raw = DB::table('patient_company_links')->where('id', $link->id)->sole();
    expect($raw->context_encrypted)->not->toContain('Empregador sintético A')->not->toContain('Estabelecimento A');
    expect($link->context_encrypted['version'])->toBe(1);
    expect($link->toArray())->not->toHaveKey('context_encrypted');
    $this->context->run($this->a['tenant'], function (): void {
        $this->company->update(['name' => 'Empregador renomeado']);
        $this->unit->update(['name' => 'Estabelecimento renomeado']);
    });
    $this->get($this->base)->assertOk()->assertSee('Empregador sintético A')->assertSee('Estabelecimento A')->assertDontSee('Empregador renomeado')->assertDontSee('Estabelecimento renomeado');
});
