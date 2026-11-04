<?php

declare(strict_types=1);

use App\Domain\Patients\Actions\ReadPatientConsentEvidence;
use App\Domain\Patients\Actions\RecordPatientConsent;
use App\Domain\Patients\Enums\ConsentDecision;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Patients\Services\PatientMergeGuard;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
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
            $clinic['user']->givePermissionTo(['patients.view', 'consents.view', 'consents.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    [$this->patientA, $this->otherA] = $this->context->run($this->a['tenant'], fn () => [Patient::query()->create(['name' => 'Paciente sintético A']), Patient::query()->create(['name' => 'Outro paciente sintético A'])]);
    $this->patientB = $this->context->run($this->b['tenant'], fn () => Patient::query()->create(['name' => 'Paciente sintético B']));
    $this->input = ['purpose' => 'Finalidade sintética', 'legal_basis' => 'Base sintética aprovada', 'policy_version' => 'synthetic-v1', 'policy_text' => "Termo sintético.\nSem conteúdo clínico.", 'evidence_note' => 'Conferência sintética com pessoa fictícia.', 'decision' => 'granted', 'attested' => '1'];
    $this->consentA = $this->context->run($this->a['tenant'], function (): PatientConsent {
        auth()->setUser($this->a['user']->fresh());

        return app(RecordPatientConsent::class)->execute($this->patientA, $this->input);
    });
    $this->consentB = $this->context->run($this->b['tenant'], function (): PatientConsent {
        auth()->setUser($this->b['user']->fresh());

        return app(RecordPatientConsent::class)->execute($this->patientB, ['purpose' => 'Finalidade exclusiva B'] + $this->input);
    });
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->patientA->public_id.'/consents';
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('records encrypted evidence hash actor and UTC time while preserving grants refusals and withdrawals', function (): void {
    $raw = DB::table('patient_consents')->where('id', $this->consentA->id)->sole();
    expect($raw->evidence_encrypted)->not->toContain('Termo sintético')->not->toContain('Conferência sintética');
    expect($raw->policy_text_hash)->toBe(hash('sha256', $this->input['policy_text']));
    expect((int) $raw->recorded_by)->toBe((int) $this->a['user']->id);
    expect($raw->source)->toBe('staff_recorded');
    $this->get('http://'.$this->a['host'].'/patients/'.$this->patientA->public_id)->assertOk()->assertSee('Consentimentos e termos');
    $this->get($this->base.'/create')->assertOk()->assertSee('Registrar manifestação');
    foreach (['withdrawn', 'refused', 'acknowledged'] as $decision) {
        $this->post($this->base, ['decision' => $decision] + $this->input)->assertRedirect();
    }
    $this->get($this->base)->assertOk()->assertSee('Consentimento concedido')->assertSee('Consentimento retirado')->assertSee('Consentimento recusado')->assertSee('Ciência de termos')->assertDontSee('Finalidade exclusiva B')->assertHeader('Cache-Control', 'no-store, private');
    $this->get($this->base.'/'.$this->consentA->id)->assertOk()->assertSee($this->input['policy_text'])->assertSee($this->input['evidence_note'])->assertHeader('Cache-Control', 'no-store, private');
    $this->context->run($this->a['tenant'], function (): void {
        expect(PatientConsent::query()->count())->toBe(4);
        expect($this->consentA->fresh()->decision)->toBe(ConsentDecision::Granted);
        expect($this->consentA->fresh()->toArray())->not->toHaveKey('evidence_encrypted');
        expect($this->consentA->fresh()->occurred_at->timezoneName)->toBe('UTC');
    });
    $events = DB::table('activity_log')->where('subject_type', PatientConsent::class)->where('subject_id', $this->consentA->id)->get();
    expect($events->pluck('event')->all())->toBe(['patient_consent_recorded', 'patient_consent_evidence_viewed']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->putJson($this->base.'/'.$this->consentA->id, $this->input)->assertStatus(405);
    $this->deleteJson($this->base.'/'.$this->consentA->id)->assertStatus(405);
});

it('refuses wrong patients foreign clinics and foreign action models', function (): void {
    $this->get('http://'.$this->a['host'].'/patients/'.$this->otherA->public_id.'/consents/'.$this->consentA->id)->assertNotFound();
    $this->get($this->base.'/'.$this->consentB->id)->assertNotFound();
    $foreign = 'http://'.$this->a['host'].'/patients/'.$this->patientB->public_id.'/consents';
    $this->get($foreign)->assertNotFound();
    $this->postJson($foreign, $this->input)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RecordPatientConsent::class)->execute($this->patientB, $this->input))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(ReadPatientConsentEvidence::class)->execute($this->consentB))->toThrow(ModelNotFoundException::class);
    });
});

it('requires explicit consent permissions patient visibility and confirmed second factor', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('consents.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Registrar manifestação');
    $this->get($this->base.'/'.$this->consentA->id)->assertOk();
    $this->get($this->base.'/create')->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RecordPatientConsent::class)->execute($this->patientA, $this->input))->toThrow(AuthorizationException::class);
        $this->a['user']->revokePermissionTo('consents.view');
    });
    $this->get($this->base)->assertForbidden();
    $this->get($this->base.'/'.$this->consentA->id)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['consents.view', 'consents.manage']);
        $this->a['user']->revokePermissionTo('patients.view');
    });
    $this->get($this->base)->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('patients.view');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
});

it('rejects actor time ownership hash and ciphertext injection', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'patient_id' => $this->patientB->id, 'id' => 99, 'recorded_by' => $this->b['user']->id, 'source' => 'patient_portal', 'occurred_at' => '2000-01-01', 'policy_text_hash' => str_repeat('a', 64), 'evidence_encrypted' => 'injected', 'created_at' => '2000-01-01', 'updated_at' => '2000-01-01'] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(DB::table('patient_consents')->count())->toBe(2);
});

it('validates explicit manifestation attestation and bounded evidence without flashing personal text', function (): void {
    foreach (['purpose' => ['', str_repeat('A', 81), ['invalid']], 'legal_basis' => ['', str_repeat('A', 81)], 'policy_version' => ['', str_repeat('A', 41)], 'policy_text' => ['', str_repeat('😀', 16001)], 'evidence_note' => ['', str_repeat('A', 4001)], 'decision' => ['', 'automatic', ['invalid']], 'attested' => [false]] as $field => $values) {
        foreach ($values as $value) {
            $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    $this->from($this->base.'/create')->post($this->base, ['decision' => 'invalid'] + $this->input)->assertSessionHasErrors('decision');
    foreach (['purpose', 'legal_basis', 'policy_version', 'policy_text', 'evidence_note'] as $field) {
        expect(session()->getOldInput($field))->toBeNull();
    }
    $this->get($this->base.'/create')->assertOk()->assertDontSee($this->input['policy_text'])->assertDontSee($this->input['evidence_note']);
});

it('enforces immutable events composite foreign keys and context requirements', function (): void {
    expect(fn () => PatientConsent::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->consentA->fresh()->forceFill(['decision' => 'withdrawn'])->save())->toThrow(LogicException::class);
        expect(fn () => $this->consentA->fresh()->forceFill(['patient_id' => $this->otherA->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->consentA->fresh()->forceFill(['tenant_id' => $this->b['tenant']->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->consentA->fresh()->delete())->toThrow(LogicException::class);
        $raw = $this->consentA->fresh()->getRawOriginal();
        unset($raw['id']);
        expect(fn () => DB::table('patient_consents')->insert(['patient_id' => $this->patientB->id] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('patient_consents')->insert(['recorded_by' => $this->b['user']->id] + $raw))->toThrow(QueryException::class);
        expect(fn () => DB::table('patients')->where('id', $this->patientA->id)->delete())->toThrow(QueryException::class);
        expect(fn () => app(PatientMergeGuard::class)->validate($this->patientA, $this->otherA))->toThrow(ValidationException::class);
    });
});

it('paginates metadata without loading evidence and escapes displayed content', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        foreach (range(1, 20) as $number) {
            app(RecordPatientConsent::class)->execute($this->patientA, ['purpose' => 'Finalidade paginada '.$number] + $this->input);
        }
    });
    $this->get($this->base)->assertOk()->assertSee('page=2', false)->assertDontSee($this->input['policy_text'])->assertDontSee($this->input['evidence_note']);
    $this->get($this->base.'?page=2')->assertOk()->assertSee('Finalidade sintética')->assertDontSee('Finalidade exclusiva B');
    $payload = ['purpose' => '<script>synthetic()</script>', 'policy_text' => '<img src=x onerror=synthetic()>', 'evidence_note' => '<script>evidence()</script>'] + $this->input;
    $this->post($this->base, $payload)->assertRedirect();
    $id = DB::table('patient_consents')->where('patient_id', $this->patientA->id)->max('id');
    $this->get($this->base.'/'.$id)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertSee('&lt;img src=x onerror=synthetic()&gt;', false)->assertDontSee('<script>evidence()</script>', false);
});

it('fails closed on missing corrupted or mismatched evidence without breaking metadata listing', function (): void {
    foreach (['corrupt', null, Crypt::encryptString('{invalid-json'), Crypt::encryptString('"not-an-envelope"')] as $ciphertext) {
        DB::table('patient_consents')->where('id', $this->consentA->id)->update(['evidence_encrypted' => $ciphertext]);
        $this->get($this->base)->assertOk();
        $this->get($this->base.'/'.$this->consentA->id)->assertNotFound();
    }
    $ciphertext = DB::table('patient_consents')->where('id', $this->consentB->id)->value('evidence_encrypted');
    DB::table('patient_consents')->where('id', $this->consentA->id)->update(['evidence_encrypted' => $ciphertext]);
    $this->get($this->base.'/'.$this->consentA->id)->assertNotFound();
    DB::table('patient_consents')->where('id', $this->consentA->id)->update(['evidence_encrypted' => $this->consentA->getRawOriginal('evidence_encrypted'), 'policy_text_hash' => str_repeat('0', 64)]);
    $this->get($this->base.'/'.$this->consentA->id)->assertNotFound();
});

it('rolls back the new manifestation when the audit fails and refuses archived patients', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base, ['decision' => 'withdrawn'] + $this->input))->toThrow(RuntimeException::class);
        expect(DB::table('patient_consents')->count())->toBe(2);
        expect(DB::table('patient_consents')->where('id', $this->consentA->id)->value('decision'))->toBe('granted');
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    $this->context->run($this->a['tenant'], function (): void {
        $this->patientA->delete();
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(RecordPatientConsent::class)->execute($this->patientA, $this->input))->toThrow(ModelNotFoundException::class);
    });
});
