<?php

declare(strict_types=1);

use App\Domain\Patients\Actions\SavePatient;
use App\Domain\Patients\Actions\SearchPatients;
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
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['patients.view', 'patients.review']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->cpf = syntheticCpf();
        $this->patient = app(SavePatient::class)->execute(['name' => 'Pessoa Sintética', 'birth_date' => '1990-01-02', 'cpf' => $this->cpf, 'email' => 'origem@example.test']);
        $this->candidate = app(SavePatient::class)->execute(['name' => '  PESSOA   SINTETICA ', 'birth_date' => '1991-02-03', 'email' => 'comparado@example.test']);
    });
    $this->foreign = $this->context->run($this->b['tenant'], fn () => app(SavePatient::class)->execute(['name' => 'Pessoa Sintética', 'cpf' => $this->cpf]));
    $this->base = 'http://'.$this->a['host'];
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('searches exact CPF through POST without exposing it in URLs views audits or session criteria', function (): void {
    $this->get($this->base.'/patients/search')->assertOk()->assertSee('Buscar por CPF');
    $response = $this->post($this->base.'/patients/search', ['mode' => 'cpf', 'cpf' => $this->cpf]);
    $response->assertRedirect($this->base.'/patients/search/results');
    $search = session('patient_search');
    expect($search['criteria'])->toHaveKeys(['mode', 'cpf_hash'])->not->toHaveKey('cpf');
    expect(json_encode($search))->not->toContain($this->cpf);
    $result = $this->get($this->base.'/patients/search/results')->assertOk()->assertSee('Pessoa Sintética')->assertDontSee($this->cpf)->assertDontSee($this->candidate->public_id)->assertDontSee($this->foreign->public_id);
    expect($result->headers->get('Cache-Control'))->toContain('no-store');
    expect(DB::table('activity_log')->where('event', 'patients_searched')->pluck('properties')->filter())->toBeEmpty();
    $this->context->run($this->a['tenant'], fn () => $this->patient->delete());
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee('Nenhum paciente cadastrado.');
});

it('normalizes name prefixes and filters optional birth date without crossing tenants', function (): void {
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => '  PESSÓA   SÍN  ', 'birth_date' => '1990-01-02'])->assertRedirect();
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee($this->patient->public_id)->assertDontSee($this->candidate->public_id)->assertDontSee($this->foreign->public_id);
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => 'pessoa'])->assertRedirect();
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee($this->patient->public_id)->assertSee($this->candidate->public_id)->assertDontSee($this->foreign->public_id);
});

it('treats SQL wildcard characters as literal name characters', function (): void {
    $this->context->run($this->a['tenant'], fn () => app(SavePatient::class)->execute(['name' => 'Pessoa_100% sintética']));
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => 'Pessoa_100%'])->assertRedirect();
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee('Pessoa_100% sintética')->assertDontSee($this->patient->public_id)->assertDontSee($this->candidate->public_id);
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => "' OR 1=1 --"])->assertRedirect();
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee('Nenhum paciente cadastrado.');
});

it('expires search criteria and rejects session criteria belonging to another tenant or user', function (): void {
    $this->get($this->base.'/patients/search/results')->assertRedirect($this->base.'/patients/search');
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => 'pessoa'])->assertRedirect();
    $search = session('patient_search');
    $this->withSession(['patient_search' => array_replace($search, ['tenant_id' => $this->b['tenant']->id])])->get($this->base.'/patients/search/results')->assertRedirect($this->base.'/patients/search');
    $this->withSession(['patient_search' => array_replace($search, ['user_id' => $this->b['user']->id])])->get($this->base.'/patients/search/results')->assertRedirect($this->base.'/patients/search');
    $this->withSession(['patient_search' => array_replace($search, ['expires_at' => now()->getTimestamp()])])->get($this->base.'/patients/search/results')->assertRedirect($this->base.'/patients/search');
    expect(session('patient_search'))->toBeNull();
});

it('validates search input without flashing personal values and fails closed without the HMAC key', function (): void {
    $this->post($this->base.'/patients/search', ['mode' => 'cpf', 'cpf' => '00000000000'])->assertSessionHasErrors('cpf')->assertSessionMissing('_old_input.cpf');
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => 'ab', 'birth_date' => '1990-02-30'])->assertSessionHasErrors(['name', 'birth_date'])->assertSessionMissing('_old_input.name')->assertSessionMissing('_old_input.birth_date');
    $this->postJson($this->base.'/patients/search', ['mode' => 'all'])->assertUnprocessable();
    $this->postJson($this->base.'/patients/search', ['mode' => 'cpf', 'cpf' => $this->cpf, 'tenant_id' => $this->b['tenant']->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        config(['identity.cpf_search_key' => null]);
        expect(fn () => app(SearchPatients::class)->criteria(['mode' => 'cpf', 'cpf' => $this->cpf]))->toThrow(RuntimeException::class);
    });
    expect(fn () => app(SearchPatients::class)->execute(['mode' => 'name', 'normalized_name' => 'pessoa', 'birth_date' => null]))->toThrow(MissingTenant::class);
});

it('lists homonyms and compares identification without altering records or disclosing CPF', function (): void {
    $before = DB::table('patients')->orderBy('id')->get()->toJson();
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/duplicates')->assertOk()->assertSee($this->candidate->public_id)->assertDontSee($this->foreign->public_id)->assertSee('não une nem altera');
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/compare/'.$this->candidate->public_id)->assertOk()->assertSee('origem@example.test')->assertSee('comparado@example.test')->assertDontSee($this->cpf)->assertSee('não registra uma decisão');
    expect(DB::table('patients')->orderBy('id')->get()->toJson())->toBe($before);
    expect(DB::table('activity_log')->where('event', 'patient_comparison_viewed')->orderBy('id')->pluck('subject_id')->map(fn ($id) => (int) $id)->all())->toBe([(int) $this->patient->id, (int) $this->candidate->id]);
});

it('denies foreign and self comparisons and enforces review permission independently', function (): void {
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/compare/'.$this->foreign->public_id)->assertNotFound();
    $this->get($this->base.'/patients/'.$this->foreign->public_id.'/duplicates')->assertNotFound();
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/compare/'.$this->patient->public_id)->assertNotFound();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.review'));
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/duplicates')->assertForbidden();
    $this->get($this->base.'/patients/'.$this->patient->public_id.'/compare/'.$this->candidate->public_id)->assertForbidden();
    $this->get($this->base.'/patients/search')->assertOk();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.view'));
    $this->get($this->base.'/patients/search')->assertForbidden();
    $this->postJson($this->base.'/patients/search', ['mode' => 'name', 'name' => 'pessoa'])->assertForbidden();
});

it('paginates search results without putting search terms in pagination links', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        foreach (range(1, 21) as $number) {
            app(SavePatient::class)->execute(['name' => 'Paginação sintética '.$number]);
        }
    });
    $this->post($this->base.'/patients/search', ['mode' => 'name', 'name' => 'paginação'])->assertRedirect();
    $this->get($this->base.'/patients/search/results')->assertOk()->assertSee('/patients/search/results?page=2', false)->assertViewHas('patients', fn ($patients) => parse_url($patients->url(2), PHP_URL_QUERY) === 'page=2');
    $this->get($this->base.'/patients/search/results?page=2')->assertOk()->assertViewHas('patients', fn ($patients) => $patients->count() === 1 && $patients->total() === 21);
});
