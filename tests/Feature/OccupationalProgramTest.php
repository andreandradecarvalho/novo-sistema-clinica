<?php

declare(strict_types=1);

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Occupational\Actions\ReadOccupationalProgram;
use App\Domain\Occupational\Actions\RegisterOccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
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
            $clinic['user']->givePermissionTo(['companies.view', 'professionals.view', 'programs.view', 'programs.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    $this->input = ['version' => 'synthetic-v1', 'valid_from' => '2026-11-04', 'valid_until' => null, 'source_reference' => 'Referência sintética informada pela clínica', 'context' => "Contexto sintético.\nSem dados de pacientes."];
    [$this->companyA, $this->otherA, $this->professionalA, $this->programA] = $this->context->run($this->a['tenant'], function (): array {
        $company = Company::query()->create(['name' => 'Empresa sintética A', 'is_active' => true]);
        $other = Company::query()->create(['name' => 'Outra empresa A', 'is_active' => true]);
        $professional = new Professional;
        $professional->user_id = $this->a['user']->id;
        $professional->save();
        auth()->setUser($this->a['user']->fresh());
        $program = app(RegisterOccupationalProgram::class)->execute($company, $professional, $this->input);

        return [$company, $other, $professional, $program];
    });
    [$this->companyB, $this->professionalB, $this->programB] = $this->context->run($this->b['tenant'], function (): array {
        $company = Company::query()->create(['name' => 'Empresa sintética B', 'is_active' => true]);
        $professional = new Professional;
        $professional->user_id = $this->b['user']->id;
        $professional->save();
        auth()->setUser($this->b['user']->fresh());

        return [$company, $professional, app(RegisterOccupationalProgram::class)->execute($company, $professional, ['context' => 'Contexto exclusivo B'] + $this->input)];
    });
    $this->base = 'http://'.$this->a['host'].'/companies/'.$this->companyA->public_id.'/programs';
    $this->registration = $this->base.'/register/'.$this->professionalA->id;
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('registers distinct immutable program versions with encrypted historical context and metadata only audit', function (): void {
    $this->get('http://'.$this->a['host'].'/companies/'.$this->companyA->public_id)->assertOk()->assertSee('Programas ocupacionais');
    $this->get($this->base.'/create')->assertOk()->assertSee('Selecionar responsável informado');
    $this->get($this->registration)->assertOk()->assertSee('Registrar versão de programa');
    $this->post($this->registration, ['version' => 'synthetic-v2'] + $this->input)->assertRedirect();
    $this->get($this->base)->assertOk()->assertSee('synthetic-v1')->assertSee('synthetic-v2')->assertDontSee($this->input['context'])->assertHeader('Cache-Control', 'no-store, private');
    $raw = DB::table('occupational_programs')->where('id', $this->programA->id)->sole();
    expect($raw->context_encrypted)->not->toContain('Contexto sintético')->not->toContain('Empresa sintética A');
    expect($raw->company_unit_id)->toBeNull();
    $this->context->run($this->a['tenant'], function (): void {
        $this->companyA->update(['name' => 'Empresa renomeada']);
        $this->a['user']->update(['name' => 'Profissional renomeado']);
        expect($this->programA->fresh()->toArray())->not->toHaveKey('context_encrypted');
    });
    $response = $this->get($this->base.'/'.$this->programA->id)->assertOk()->assertSee('Empresa sintética A')->assertSee('Equipe sintética')->assertSee('Não informado')->assertSee($this->input['context'])->assertHeader('Cache-Control', 'no-store, private');
    expect($response->viewData('context')['professional_name'])->toBe('Equipe sintética');
    $events = DB::table('activity_log')->where('subject_type', OccupationalProgram::class)->where('subject_id', $this->programA->id)->get();
    expect($events->pluck('event')->all())->toBe(['occupational_program_registered', 'occupational_program_viewed']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->putJson($this->base.'/'.$this->programA->id, $this->input)->assertStatus(405);
    $this->deleteJson($this->base.'/'.$this->programA->id)->assertStatus(405);
});

it('isolates company programs route bindings and professionals across clinics', function (): void {
    $this->get('http://'.$this->a['host'].'/companies/'.$this->otherA->public_id.'/programs/'.$this->programA->id)->assertNotFound();
    $this->get($this->base.'/'.$this->programB->id)->assertNotFound();
    $this->get($this->base.'/register/'.$this->professionalB->id)->assertNotFound();
    $this->postJson($this->base.'/register/'.$this->professionalB->id, $this->input)->assertNotFound();
    $foreign = 'http://'.$this->a['host'].'/companies/'.$this->companyB->public_id.'/programs';
    $this->get($foreign)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RegisterOccupationalProgram::class)->execute($this->companyB, $this->professionalA, $this->input))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(RegisterOccupationalProgram::class)->execute($this->companyA, $this->professionalB, $this->input))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(ReadOccupationalProgram::class)->execute($this->programB))->toThrow(ModelNotFoundException::class);
    });
});

it('enforces read manage company professional and second factor permissions independently', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('programs.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Registrar versão');
    $this->get($this->base.'/'.$this->programA->id)->assertOk();
    $this->get($this->base.'/create')->assertForbidden();
    $this->postJson($this->registration, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RegisterOccupationalProgram::class)->execute($this->companyA, $this->professionalA, $this->input))->toThrow(AuthorizationException::class);
        $this->a['user']->givePermissionTo('programs.manage');
        $this->a['user']->revokePermissionTo('professionals.view');
    });
    $this->get($this->base.'/create')->assertForbidden();
    $this->postJson($this->registration, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('programs.view'));
    $this->get($this->base)->assertForbidden();
    $this->get($this->base.'/'.$this->programA->id)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['programs.view', 'professionals.view']);
        $this->a['user']->revokePermissionTo('companies.view');
    });
    $this->get($this->base)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('companies.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
    $this->postJson($this->registration, $this->input)->assertForbidden();
});

it('refuses duplicate versions only within the same tenant and company', function (): void {
    $this->postJson($this->registration, $this->input)->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->post('http://'.$this->a['host'].'/companies/'.$this->otherA->public_id.'/programs/register/'.$this->professionalA->id, $this->input)->assertRedirect();
    expect(DB::table('occupational_programs')->count())->toBe(3);
});

it('rejects owner establishment approval and encrypted payload injection', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'company_id' => $this->companyB->id, 'company_unit_id' => 1, 'responsible_professional_id' => $this->professionalB->id, 'id' => 99, 'context_encrypted' => 'injected', 'approved_by' => 1, 'approved_at' => '2026-11-04', 'created_at' => '2026-11-04', 'updated_at' => '2026-11-04'] as $field => $value) {
        $this->postJson($this->registration, [$field => $value, 'version' => 'new'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

it('validates bounds dates and sensitive old input', function (): void {
    foreach (['version' => ['', str_repeat('A', 41), ['invalid']], 'source_reference' => ['', str_repeat('A', 191)], 'context' => ['', str_repeat('😀', 16001), ['invalid']], 'valid_from' => ['', '2026-02-30'], 'valid_until' => ['2026-11-03']] as $field => $values) {
        foreach ($values as $value) {
            $this->postJson($this->registration, [$field => $value, 'version' => $field === 'version' ? $value : 'new'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    $this->from($this->registration)->post($this->registration, ['version' => ['invalid'], 'valid_from' => ['invalid']] + $this->input)->assertSessionHasErrors(['version', 'valid_from']);
    expect(session()->getOldInput('context'))->toBeNull()->and(session()->getOldInput('source_reference'))->toBeNull();
    $this->get($this->registration)->assertOk();
});

it('enforces composite foreign keys immutable owners dates and missing context', function (): void {
    expect(fn () => OccupationalProgram::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->programA->fresh()->forceFill(['version' => 'changed'])->save())->toThrow(LogicException::class);
        expect(fn () => $this->programA->fresh()->forceFill(['company_id' => $this->otherA->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->programA->fresh()->delete())->toThrow(LogicException::class);
        $raw = $this->programA->fresh()->getRawOriginal();
        unset($raw['id']);
        expect(fn () => DB::table('occupational_programs')->insert(['company_id' => $this->companyB->id, 'version' => 'foreign-company'] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('occupational_programs')->insert(['responsible_professional_id' => $this->professionalB->id, 'version' => 'foreign-professional'] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('occupational_programs')->insert(['valid_until' => '2026-11-03', 'version' => 'invalid-dates'] + $raw))->toThrow(QueryException::class);
    });
});

it('keeps historical references readable but refuses inactive companies and professionals for new registrations', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->companyA->update(['is_active' => false]));
    $this->get($this->base.'/'.$this->programA->id)->assertOk();
    $this->postJson($this->registration, ['version' => 'new'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->context->run($this->a['tenant'], function (): void {
        $this->companyA->update(['is_active' => true]);
        $inactive = User::query()->create(['name' => 'Responsável sintético inativo', 'email' => 'inativo@example.test', 'password' => 'Aa1!'.bin2hex(random_bytes(16))]);
        $inactive->forceFill(['is_active' => false])->save();
        $professional = new Professional;
        $professional->user_id = $inactive->id;
        $professional->save();
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RegisterOccupationalProgram::class)->execute($this->companyA, $professional, ['version' => 'new'] + $this->input))->toThrow(ModelNotFoundException::class);
    });
});

it('fails closed on corrupted or substituted context without exposing it in the listing', function (): void {
    $foreign = DB::table('occupational_programs')->where('id', $this->programB->id)->value('context_encrypted');
    foreach (['corrupt', null, Crypt::encryptString('{invalid-json'), $foreign] as $value) {
        DB::table('occupational_programs')->where('id', $this->programA->id)->update(['context_encrypted' => $value]);
        $this->get($this->base)->assertOk()->assertDontSee('Contexto exclusivo B');
        $this->get($this->base.'/'.$this->programA->id)->assertNotFound();
    }
});

it('rolls back a new version when the audit fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->registration, ['version' => 'new'] + $this->input))->toThrow(RuntimeException::class);
        expect(DB::table('occupational_programs')->count())->toBe(2);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('paginates only the selected company and escapes preserved context', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        foreach (range(1, 20) as $number) {
            app(RegisterOccupationalProgram::class)->execute($this->companyA, $this->professionalA, ['version' => 'page-'.$number] + $this->input);
        }
    });
    $this->get($this->base)->assertOk()->assertSee('page=2', false)->assertDontSee('Empresa sintética B');
    $this->get($this->base.'?page=2')->assertOk()->assertSee('synthetic-v1');
    $this->post($this->registration, ['version' => 'escaped', 'source_reference' => '<script>synthetic()</script>', 'context' => '<img src=x onerror=synthetic()>'] + $this->input)->assertRedirect();
    $id = DB::table('occupational_programs')->where('company_id', $this->companyA->id)->max('id');
    $this->get($this->base.'/'.$id)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertSee('&lt;img src=x onerror=synthetic()&gt;', false)->assertDontSee('<img src=x onerror=synthetic()>', false);
});
