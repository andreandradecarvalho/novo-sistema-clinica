<?php

declare(strict_types=1);

use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Services\PatientIdentity;
use App\Domain\Shared\ValueObjects\Cpf;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Clinics;

beforeEach(function (): void {
    $this->withoutVite();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    DB::beginTransaction();
    $this->seed();
    config(['identity.cpf_search_key' => bin2hex(random_bytes(32))]);
    $this->context = app(TenantContext::class);
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['patients.view', 'patients.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    $this->base = 'http://'.$this->a['host'];
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates an encrypted patient identity without disclosing CPF in HTML or audit', function (): void {
    $cpf = syntheticCpf();
    $this->post($this->base.'/patients', ['name' => 'Pessoa sintética', 'cpf' => substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9)])->assertRedirect();
    $patient = $this->context->run($this->a['tenant'], fn () => Patient::query()->sole());
    $raw = DB::table('patients')->where('id', $patient->id)->sole();
    expect($raw->cpf_encrypted)->not->toContain($cpf);
    expect($raw->cpf_hash)->not->toBe(hash('sha256', $cpf));
    expect($patient->cpf_encrypted)->toBe($cpf);
    expect($patient->toArray())->not->toHaveKeys(['cpf_hash', 'cpf_encrypted']);
    $this->get($this->base.'/patients/'.$patient->public_id)->assertOk()->assertDontSee($cpf);
    $audit = DB::table('activity_log')->where('log_name', 'patients')->get();
    expect($audit->pluck('properties')->filter())->toBeEmpty();
    expect($audit->pluck('event')->all())->toBe(['patient_created', 'patient_viewed']);
});

it('enforces CPF uniqueness in the database while isolating clinics', function (): void {
    $cpf = syntheticCpf();
    $this->post($this->base.'/patients', ['name' => 'Pessoa sintética A', 'cpf' => $cpf])->assertRedirect();
    $this->postJson($this->base.'/patients', ['name' => 'Duplicata sintética', 'cpf' => $cpf])->assertUnprocessable()->assertJsonValidationErrors('cpf');
    $hashA = $this->context->run($this->a['tenant'], fn () => app(PatientIdentity::class)->hash(new Cpf($cpf)));
    $hashB = $this->context->run($this->b['tenant'], fn () => app(PatientIdentity::class)->hash(new Cpf($cpf)));
    expect($hashA)->not->toBe($hashB);
    $this->postJson($this->base.'/logout')->assertSuccessful();
    $baseB = 'http://'.$this->b['host'];
    $this->postJson($baseB.'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
    $this->post($baseB.'/patients', ['name' => 'Pessoa sintética B', 'cpf' => $cpf])->assertRedirect();
    expect(DB::table('patients')->count())->toBe(2);
});

it('supports partial registration and preserves CPF on an update with an empty CPF', function (): void {
    $this->post($this->base.'/patients', ['name' => 'Cadastro parcial'])->assertRedirect();
    $patient = $this->context->run($this->a['tenant'], fn () => Patient::query()->sole());
    expect($patient->is_partial)->toBeTrue();
    $cpf = syntheticCpf();
    $this->put($this->base.'/patients/'.$patient->public_id, ['name' => 'Cadastro completo', 'cpf' => $cpf])->assertRedirect();
    $this->put($this->base.'/patients/'.$patient->public_id, ['name' => 'Nome corrigido', 'cpf' => ''])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($patient, $cpf): void {
        expect($patient->fresh()->cpf_encrypted)->toBe($cpf);
        expect($patient->fresh()->is_partial)->toBeFalse();
    });
});

it('rejects foreign patient bindings and ownership input and fails closed without context', function (): void {
    $foreign = $this->context->run($this->b['tenant'], fn () => Patient::query()->create(['name' => 'Pessoa de outra clínica']));
    $this->get($this->base.'/patients/'.$foreign->public_id)->assertNotFound();
    $this->get($this->base.'/patients/'.$foreign->public_id.'/edit')->assertNotFound();
    $this->putJson($this->base.'/patients/'.$foreign->public_id, ['name' => 'Inválido'])->assertNotFound();
    $this->postJson($this->base.'/patients', ['name' => 'Inválido', 'tenant_id' => $this->b['tenant']->id])->assertUnprocessable();
    expect(fn () => Patient::query()->count())->toThrow(MissingTenant::class);
});

it('denies patient access without permission or confirmed second factor', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.view'));
    $this->get($this->base.'/patients')->assertForbidden();
    $this->postJson($this->base.'/patients', ['name' => 'Inválido'])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('patients.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base.'/patients')->assertForbidden();
});

it('validates CPF and dates and keeps personal inputs out of flashed session data', function (): void {
    $this->post($this->base.'/patients', ['name' => 'Pessoa sintética', 'cpf' => '00000000000', 'birth_date' => '2999-01-01'])
        ->assertSessionHasErrors(['cpf', 'birth_date'])->assertSessionMissing('_old_input.cpf')->assertSessionMissing('_old_input.name');
    $this->get($this->base.'/patients/create')->assertOk()->assertSee('Novo paciente');
    $this->get($this->base.'/patients')->assertOk()->assertSee('Nenhum paciente cadastrado');
});

it('fails closed before persisting a CPF without the separate search key', function (): void {
    config(['identity.cpf_search_key' => null]);
    $this->withoutExceptionHandling();
    try {
        $this->postJson($this->base.'/patients', ['name' => 'Pessoa sintética', 'cpf' => syntheticCpf()]);
        test()->fail('Missing key must not allow a CPF write.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Configure the private CPF search key before using patient identity.');
    }
    expect(DB::table('patients')->count())->toBe(0);
});
