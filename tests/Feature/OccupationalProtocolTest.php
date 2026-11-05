<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Actions\ApproveOccupationalProtocol;
use App\Domain\Occupational\Actions\CreateOccupationalProtocol;
use App\Domain\Occupational\Actions\RegisterOccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Actions\ProvisionTenant;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
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
    $this->input = ['code' => 'PROTO-SYN', 'valid_from' => '2026-11-04', 'valid_until' => null, 'source_reference' => 'Fonte sintética da clínica', 'job_title' => 'Função sintética', 'risk_context' => 'Contexto sintético, sem dados de pacientes.', 'exam_codes' => "EX-A\nEX-B"];
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            // Explicit synthetic grant; provisioning never grants protocols.approve.
            $clinic['user']->givePermissionTo(['companies.view', 'professionals.view', 'programs.view', 'programs.manage', 'catalog.view', 'protocols.view', 'protocols.manage', 'protocols.approve', 'clinical.access']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    [$this->programA, $this->otherA, $this->professionalA, $this->protocolA, $this->examA] = $this->context->run($this->a['tenant'], function (): array {
        auth()->setUser($this->a['user']->fresh());
        $company = Company::query()->create(['name' => 'Empresa sintética A', 'is_active' => true]);
        $professional = new Professional;
        $professional->user_id = $this->a['user']->id;
        $professional->fill(['council' => 'CRM', 'council_uf' => 'MG', 'council_number' => 'SYN-A'])->save();
        $exam = ExamType::query()->create(['code' => 'EX-A', 'name' => 'Exame sintético A', 'is_active' => true]);
        ExamType::query()->create(['code' => 'EX-B', 'name' => 'Exame sintético B', 'is_active' => true]);
        $values = ['version' => 'syn-v1', 'valid_from' => '2026-01-01', 'valid_until' => null, 'source_reference' => 'Fonte sintética', 'context' => 'Contexto sintético'];
        $program = app(RegisterOccupationalProgram::class)->execute($company, $professional, $values);
        $other = app(RegisterOccupationalProgram::class)->execute($company, $professional, ['version' => 'syn-v2'] + $values);

        return [$program, $other, $professional, app(CreateOccupationalProtocol::class)->execute($program, $this->input), $exam];
    });
    [$this->programB, $this->professionalB, $this->protocolB] = $this->context->run($this->b['tenant'], function (): array {
        auth()->setUser($this->b['user']->fresh());
        $company = Company::query()->create(['name' => 'Empresa sintética B', 'is_active' => true]);
        $professional = new Professional;
        $professional->user_id = $this->b['user']->id;
        $professional->fill(['council' => 'CRM', 'council_uf' => 'SP', 'council_number' => 'SYN-B'])->save();
        ExamType::query()->create(['code' => 'FOREIGN', 'name' => 'Exame exclusivo B', 'is_active' => true]);
        $program = app(RegisterOccupationalProgram::class)->execute($company, $professional, ['version' => 'syn-v1', 'valid_from' => '2026-01-01', 'source_reference' => 'Fonte B', 'context' => 'Contexto B']);

        return [$program, $professional, app(CreateOccupationalProtocol::class)->execute($program, ['exam_codes' => 'FOREIGN'] + $this->input)];
    });
    $this->base = 'http://'.$this->a['host'].'/programs/'.$this->programA->id.'/protocols';
    $this->url = $this->base.'/'.$this->protocolA->id;
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson('http://'.$this->a['host'].'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('registers pending versions and approves only the authenticated professional without changing rules', function (): void {
    $this->get($this->base)->assertOk()->assertSee('PROTO-SYN')->assertDontSee('Exame exclusivo B');
    $this->get($this->base.'/create')->assertOk()->assertSee('Novo protocolo');
    $this->post($this->base, ['code' => '  new-syn  '] + $this->input)->assertRedirect();
    expect(DB::table('occupational_protocols')->where('code', 'NEW-SYN')->count())->toBe(1);
    $this->get($this->url)->assertOk()->assertSee('Exame sintético A')->assertSee('Confirmar aprovação')->assertHeader('Cache-Control', 'no-store, private');
    $original = DB::table('occupational_protocols')->where('id', $this->protocolA->id)->sole();
    $this->post($this->url.'/approve', ['attested' => '1'])->assertRedirect();
    $approved = DB::table('occupational_protocols')->where('id', $this->protocolA->id)->sole();
    expect((int) $approved->approved_by)->toBe((int) $this->professionalA->id)->and($approved->approved_at)->not->toBeNull()->and($approved->rules)->toBe($original->rules);
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    $this->get($this->url.'/versions')->assertOk()->assertSee('Nova versão');
    $revision = $this->input;
    unset($revision['code']);
    $this->post($this->url.'/versions', $revision)->assertRedirect();
    $latest = DB::table('occupational_protocols')->where('tenant_id', $this->a['tenant']->id)->where('code', 'PROTO-SYN')->orderByDesc('version')->first();
    expect((int) $latest->version)->toBe(2)->and($latest->approved_by)->toBeNull()->and($latest->approved_at)->toBeNull();
    expect(DB::table('occupational_protocols')->where('id', $this->protocolA->id)->value('approved_by'))->toBe($approved->approved_by);
    $events = DB::table('activity_log')->where('subject_type', OccupationalProtocol::class)->where('subject_id', $this->protocolA->id)->get();
    expect($events->pluck('event')->all())->toContain('occupational_protocol_created', 'occupational_protocol_approved');
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->putJson($this->url, $this->input)->assertStatus(405);
    $this->deleteJson($this->url)->assertStatus(405);
});

it('requires explicit approval permission and never grants it automatically through tenant provisioning', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('protocols.approve'));
    $this->get($this->url)->assertOk()->assertDontSee('Confirmar aprovação');
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(ApproveOccupationalProtocol::class)->execute($this->programA, $this->protocolA))->toThrow(AuthorizationException::class);
    });
    app(ProvisionTenant::class)->execute(['name' => $this->a['tenant']->name, 'slug' => $this->a['tenant']->slug, 'domain' => $this->a['host']]);
    $this->context->run($this->a['tenant'], function (): void {
        expect(Role::findByName('clinic_admin')->hasPermissionTo('protocols.approve'))->toBeFalse();
        expect(Role::findByName('doctor')->hasPermissionTo('protocols.approve'))->toBeFalse();
    });
});

it('requires an own CRM registration clinical permission and recent password for approval', function (): void {
    foreach ([['council' => 'OTHER'], ['council_number' => null], ['council_uf' => null]] as $fields) {
        $this->context->run($this->a['tenant'], fn () => $this->professionalA->fresh()->forceFill($fields + ['council' => 'CRM', 'council_number' => 'SYN-A', 'council_uf' => 'MG'])->save());
        $this->postJson($this->url.'/approve', ['attested' => '1'])->assertForbidden();
    }
    $this->context->run($this->a['tenant'], function (): void {
        $this->professionalA->fresh()->forceFill(['council' => 'CRM', 'council_number' => 'SYN-A', 'council_uf' => 'MG'])->save();
        $this->a['user']->revokePermissionTo('clinical.access');
    });
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo('clinical.access'));
    $this->withSession(['auth.password_confirmed_at' => 0])->postJson($this->url.'/approve', ['attested' => '1'])->assertStatus(423);
});

it('refuses crossed programs protocols and catalogue references through HTTP and actions', function (): void {
    $this->get($this->base.'/'.$this->protocolB->id)->assertNotFound();
    $this->get('http://'.$this->a['host'].'/programs/'.$this->otherA->id.'/protocols/'.$this->protocolA->id)->assertNotFound();
    $this->get('http://'.$this->a['host'].'/programs/'.$this->programB->id.'/protocols')->assertNotFound();
    $this->postJson($this->base, ['code' => 'foreign-exam', 'exam_codes' => 'FOREIGN'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('exam_codes');
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateOccupationalProtocol::class)->execute($this->programB, $this->input))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(CreateOccupationalProtocol::class)->execute($this->otherA, $this->input, $this->protocolA))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(ApproveOccupationalProtocol::class)->execute($this->otherA, $this->protocolA))->toThrow(ModelNotFoundException::class);
    });
});

it('separates consultation management catalogue and two factor permissions', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('protocols.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Novo protocolo');
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('protocols.manage');
        $this->a['user']->revokePermissionTo('catalog.view');
    });
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('protocols.view'));
    $this->get($this->url)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['protocols.view', 'catalog.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->getJson($this->base)->assertForbidden();
});

it('refuses injected ownership versions rules and approvals', function (): void {
    foreach (['tenant_id' => 1, 'program_id' => $this->otherA->id, 'id' => 1, 'version' => 99, 'rules' => ['schema_version' => 1], 'approved_by' => $this->professionalB->id, 'approved_at' => '2026-11-04', 'created_at' => '2026-11-04', 'updated_at' => '2026-11-04'] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    foreach (['approved_by' => $this->professionalB->id, 'rules' => ['tampered'], 'version' => 99] as $field => $value) {
        $this->postJson($this->url.'/approve', [$field => $value, 'attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson($this->url.'/approve', [])->assertUnprocessable()->assertJsonValidationErrors('attested');
});

it('refuses stale versions duplicate codes and version overflow', function (): void {
    $this->postJson($this->base, $this->input)->assertUnprocessable()->assertJsonValidationErrors('code');
    $revision = $this->input;
    unset($revision['code']);
    $this->post($this->url.'/versions', $revision)->assertRedirect();
    $this->postJson($this->url.'/versions', $revision)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    $latest = DB::table('occupational_protocols')->where('tenant_id', $this->a['tenant']->id)->orderByDesc('id')->first();
    DB::table('occupational_protocols')->where('id', $latest->id)->update(['version' => 65535]);
    $this->postJson($this->base.'/'.$latest->id.'/versions', $revision)->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('preserves catalogue snapshots and refuses approval after catalogue changes or company deactivation', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->examA->update(['name' => 'Nome posterior']));
    $this->get($this->url)->assertOk()->assertSee('Exame sintético A')->assertDontSee('Nome posterior');
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    $this->context->run($this->a['tenant'], fn () => $this->examA->update(['name' => 'Exame sintético A', 'is_active' => false]));
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    $this->context->run($this->a['tenant'], function (): void {
        $this->examA->update(['is_active' => true]);
        $this->programA->company->update(['is_active' => false]);
    });
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    $this->postJson($this->base, ['code' => 'NEW'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('validates dates structured catalogue input limits and sensitive old input', function (): void {
    foreach (['code' => ['', str_repeat('A', 61), 'bad code'], 'valid_from' => ['', '2026-02-30'], 'valid_until' => ['2026-11-03'], 'risk_context' => ['', str_repeat('A', 4001), ['bad']], 'source_reference' => ['', str_repeat('A', 191)], 'exam_codes' => ['', "EX-A\nex-a", str_repeat('X', 21), "EX-A\n", ['bad']]] as $field => $values) {
        foreach ($values as $value) {
            // A trailing newline is intentionally accepted after trimming.
            if ($field === 'exam_codes' && $value === "EX-A\n") {
                continue;
            }
            $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    $this->postJson($this->base, ['valid_from' => '2025-12-31'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('valid_until');
    $this->from($this->base.'/create')->post($this->base, ['code' => ['bad']] + $this->input)->assertSessionHasErrors('code');
    expect(session()->getOldInput('risk_context'))->toBeNull()->and(session()->getOldInput('exam_codes'))->toBeNull();
    $this->get($this->base.'/create')->assertOk();
});

it('enforces model immutability context and cross tenant foreign keys', function (): void {
    expect(fn () => OccupationalProtocol::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->protocolA->fresh()->forceFill(['rules' => []])->save())->toThrow(LogicException::class);
        expect(fn () => $this->protocolA->fresh()->forceFill(['approved_by' => $this->professionalA->id, 'approved_at' => now()])->save())->toThrow(LogicException::class);
        expect(fn () => $this->protocolA->fresh()->delete())->toThrow(LogicException::class);
        $raw = $this->protocolA->fresh()->getRawOriginal();
        unset($raw['id']);
        expect(fn () => DB::table('occupational_protocols')->insert(['program_id' => $this->programB->id, 'code' => 'FK-PROGRAM'] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('occupational_protocols')->insert(['approved_by' => $this->professionalB->id, 'code' => 'FK-PROFESSIONAL'] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('occupational_programs')->where('id', $this->programA->id)->delete())->toThrow(QueryException::class);
    });
});

it('rolls back new versions and approvals when audit persistence fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base, ['code' => 'NEW'] + $this->input))->toThrow(RuntimeException::class);
        expect(fn () => $this->post($this->url.'/approve', ['attested' => '1']))->toThrow(RuntimeException::class);
        expect(DB::table('occupational_protocols')->count())->toBe(2);
        $row = DB::table('occupational_protocols')->where('id', $this->protocolA->id)->sole();
        expect($row->approved_by)->toBeNull()->and($row->approved_at)->toBeNull();
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('refuses unsupported imported rules or missing validity without approving them', function (): void {
    DB::table('occupational_protocols')->where('id', $this->protocolA->id)->update(['rules' => json_encode(['schema_version' => 99, 'exams' => []], JSON_THROW_ON_ERROR)]);
    $this->getJson($this->url)->assertUnprocessable()->assertJsonValidationErrors('rules');
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('rules');
    $rules = $this->protocolA->rules;
    $rules['exams'][0]['exam_type_id'] = (int) DB::table('exam_types')->where('tenant_id', $this->b['tenant']->id)->value('id');
    DB::table('occupational_protocols')->where('id', $this->protocolA->id)->update(['rules' => json_encode($rules, JSON_THROW_ON_ERROR)]);
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
    DB::table('occupational_protocols')->where('id', $this->protocolA->id)->update(['valid_from' => null]);
    $this->postJson($this->url.'/approve', ['attested' => '1'])->assertUnprocessable()->assertJsonValidationErrors('approval');
});

it('paginates only the selected program and escapes protocol context', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        app(CreateOccupationalProtocol::class)->execute($this->otherA, ['code' => 'OTHER-PROGRAM'] + $this->input);
        foreach (range(1, 20) as $number) {
            app(CreateOccupationalProtocol::class)->execute($this->programA, ['code' => 'PAGE-'.$number] + $this->input);
        }
    });
    $response = $this->get($this->base)->assertOk()->assertSee('page=2', false)->assertDontSee('OTHER-PROGRAM');
    expect($response->viewData('protocols')->getCollection()->pluck('program_id')->unique()->all())->toBe([$this->programA->id]);
    $this->get($this->base.'?page=2')->assertOk()->assertSee('PROTO-SYN')->assertDontSee('OTHER-PROGRAM');
    $this->post($this->base, ['code' => 'ESCAPED', 'risk_context' => '<script>synthetic()</script>', 'source_reference' => '<img src=x onerror=synthetic()>'] + $this->input)->assertRedirect();
    $id = DB::table('occupational_protocols')->where('tenant_id', $this->a['tenant']->id)->where('code', 'ESCAPED')->value('id');
    $this->get($this->base.'/'.$id)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertSee('&lt;img src=x onerror=synthetic()&gt;', false)->assertDontSee('<script>synthetic()</script>', false);
});

it('bounds protocol validity to a program with a fixed end and limits the number of catalogue items', function (): void {
    $program = $this->context->run($this->a['tenant'], function () {
        auth()->setUser($this->a['user']->fresh());

        return app(RegisterOccupationalProgram::class)->execute($this->programA->company, $this->professionalA, ['version' => 'bounded', 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'source_reference' => 'Fonte sintética', 'context' => 'Contexto sintético']);
    });
    $base = 'http://'.$this->a['host'].'/programs/'.$program->id.'/protocols';
    $this->postJson($base, $this->input)->assertUnprocessable()->assertJsonValidationErrors('valid_until');
    $this->postJson($base, ['valid_until' => '2027-01-01'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('valid_until');
    $this->post($base, ['valid_until' => '2026-12-31', 'code' => 'BOUNDED'] + $this->input)->assertRedirect();
    $codes = implode("\n", array_map(fn ($number) => 'SYN-'.$number, range(1, 101)));
    $this->postJson($this->base, ['exam_codes' => $codes] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('exam_codes');
});
