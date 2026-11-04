<?php

declare(strict_types=1);

use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Occupational\Actions\CreateEmployment;
use App\Domain\Patients\Actions\MergePatients;
use App\Domain\Patients\Actions\SavePatient;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Patients\Services\PatientMergeReview;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
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
    config(['identity.cpf_search_key' => bin2hex(random_bytes(32))]);
    $this->context = app(TenantContext::class);
    $this->a = Clinics::create();
    $this->b = Clinics::create();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['patients.view', 'patients.manage', 'patients.review', 'patients.merge']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->cpf = syntheticCpf();
        $this->survivor = app(SavePatient::class)->execute(['name' => 'Pessoa sintética', 'birth_date' => '1990-01-02', 'cpf' => $this->cpf, 'email' => 'sobrevivente@example.test']);
        $this->source = app(SavePatient::class)->execute(['name' => 'Pessoa sintética', 'birth_date' => '1990-01-02', 'email' => 'origem@example.test']);
    });
    $this->foreign = $this->context->run($this->b['tenant'], fn () => app(SavePatient::class)->execute(['name' => 'Outra pessoa sintética']));
    $this->base = 'http://'.$this->a['host'];
    $this->pair = $this->base.'/patients/'.$this->survivor->public_id.'/compare/'.$this->source->public_id;
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($this->base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
    $this->token = $this->get($this->pair)->assertOk()->viewData('reviewToken');
    $this->input = ['survivor_id' => $this->survivor->id, 'reason' => 'Conferência sintética por fonte autorizada.', 'identity_confirmed' => '1', 'review_token' => $this->token];
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('persists the human decision archives only the unlinked source and encrypts evidence', function (): void {
    $before = $this->context->run($this->a['tenant'], fn () => $this->survivor->fresh()->getRawOriginal());
    $this->post($this->pair.'/merge', $this->input)->assertRedirect($this->base.'/patients/'.$this->survivor->public_id.'/merges');
    $merge = $this->context->run($this->a['tenant'], fn () => PatientMerge::query()->sole());
    $raw = DB::table('patient_merges')->where('id', $merge->id)->sole();
    expect($raw->reason)->not->toContain('Conferência sintética');
    expect($raw->payload_snapshot)->not->toContain('origem@example.test')->not->toContain($this->cpf);
    expect($merge->reason)->toBe($this->input['reason']);
    expect($merge->toArray())->not->toHaveKeys(['reason', 'payload_snapshot']);
    $snapshot = json_decode(Crypt::decryptString($merge->payload_snapshot['source_encrypted']), true);
    expect($snapshot['patient']['email'])->toBe('origem@example.test');
    expect((int) $merge->merged_by)->toBe((int) $this->a['user']->id);
    $this->context->run($this->a['tenant'], function () use ($before): void {
        expect($this->survivor->fresh()->getRawOriginal())->toBe($before);
        expect(Patient::query()->whereKey($this->source->id)->exists())->toBeFalse();
        expect(Patient::withTrashed()->findOrFail($this->source->id)->trashed())->toBeTrue();
    });
    $this->get($this->base.'/patients/'.$this->source->public_id)->assertNotFound();
    $this->get($this->base.'/patients/'.$this->survivor->public_id.'/merges')->assertOk()->assertSee($this->input['reason'])->assertSee('origem@example.test')->assertDontSee($this->cpf);
    $events = DB::table('activity_log')->where('event', 'patients_merged')->get();
    expect($events)->toHaveCount(1);
    expect($events->first()->properties)->toBeNull();
});

it('does not repeat a merge or allow historical mutation restoration or stale patient edits', function (): void {
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    $this->postJson($this->pair.'/merge', $this->input)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        $merge = PatientMerge::query()->sole();
        expect(fn () => $merge->forceFill(['reason' => 'Alteração indevida'])->save())->toThrow(LogicException::class);
        expect(fn () => $merge->delete())->toThrow(LogicException::class);
        $archived = Patient::withTrashed()->findOrFail($this->source->id);
        expect(fn () => $archived->restore())->toThrow(LogicException::class);
        expect(fn () => app(SavePatient::class)->execute(['name' => 'Edição obsoleta'], $this->source))->toThrow(ModelNotFoundException::class);
        expect(PatientMerge::query()->count())->toBe(1);
    });
});

it('rejects CPF conflicts and known divergent birth dates without changing patients', function (): void {
    $this->postJson($this->pair.'/merge', array_replace($this->input, ['survivor_id' => $this->source->id]))->assertUnprocessable()->assertJsonValidationErrors('survivor_id');
    $this->context->run($this->a['tenant'], fn () => app(SavePatient::class)->execute(['birth_date' => '1991-01-02'], $this->source));
    $this->input['review_token'] = $this->get($this->pair)->assertOk()->viewData('reviewToken');
    $this->postJson($this->pair.'/merge', $this->input)->assertUnprocessable()->assertJsonValidationErrors('survivor_id');
    $this->context->run($this->a['tenant'], fn () => expect(PatientMerge::query()->count())->toBe(0));
    expect(DB::table('patients')->where('id', $this->source->id)->value('deleted_at'))->toBeNull();
});

it('refuses to consolidate employment history without permission to review it', function (): void {
    $link = $this->context->run($this->a['tenant'], function () {
        $company = app(SaveCompany::class)->execute(['name' => 'Empregador sintético', 'is_active' => true]);

        return app(CreateEmployment::class)->execute($this->source, $company, ['starts_on' => '2026-01-01', 'job_title' => 'Função sintética']);
    });
    $this->postJson($this->pair.'/merge', $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function () use ($link): void {
        expect((int) $link->fresh()->patient_id)->toBe((int) $this->source->id);
        expect($link->fresh()->job_title)->toBe('Função sintética');
        expect($this->source->fresh())->not->toBeNull();
        expect(PatientMerge::query()->count())->toBe(0);
    });
});

it('refuses account legacy consent and existing merge associations on the source', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        foreach (['user_id' => $this->a['user']->id, 'legacy_code' => 'SYNTHETIC', 'terms_accepted_text' => 'Termo sintético'] as $field => $value) {
            $current = $this->source->fresh();
            $current->forceFill([$field => $value])->save();
            $token = app(PatientMergeReview::class)->issue($this->a['user'], $this->survivor->fresh(), $current);
            expect(fn () => app(MergePatients::class)->execute($this->a['user'], $this->survivor, $current, array_replace($this->input, ['review_token' => $token])))->toThrow(ValidationException::class);
            $current->forceFill([$field => null])->save();
        }
        PatientMerge::query()->forceCreate(['survivor_patient_id' => $this->source->id, 'merged_patient_id' => $this->survivor->id, 'merged_by' => $this->a['user']->id, 'created_at' => now()]);
        $token = app(PatientMergeReview::class)->issue($this->a['user'], $this->survivor->fresh(), $this->source->fresh());
        expect(fn () => app(MergePatients::class)->execute($this->a['user'], $this->survivor, $this->source, array_replace($this->input, ['review_token' => $token])))->toThrow(ValidationException::class);
    });
});

it('rejects changed expired tampered or another actors comparison tokens', function (): void {
    $this->postJson($this->pair.'/merge', array_replace($this->input, ['review_token' => 'invalid-token']))->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->context->run($this->a['tenant'], fn () => app(SavePatient::class)->execute(['email' => 'alterado@example.test'], $this->source));
    $this->postJson($this->pair.'/merge', $this->input)->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->context->run($this->a['tenant'], function (): void {
        $other = Clinics::create();
        $token = app(PatientMergeReview::class)->issue($other['user'], $this->survivor->fresh(), $this->source->fresh());
        expect(fn () => app(MergePatients::class)->execute($this->a['user'], $this->survivor, $this->source, array_replace($this->input, ['review_token' => $token])))->toThrow(ValidationException::class);
    });
    $this->input['review_token'] = $this->get($this->pair)->assertOk()->viewData('reviewToken');
    $this->travel(15)->minutes();
    $this->postJson($this->pair.'/merge', $this->input)->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->travelBack();
});

it('requires independent permission second factor explicit confirmation reason and recent password', function (): void {
    $this->post($this->pair.'/merge', array_replace($this->input, ['identity_confirmed' => '0', 'reason' => '']))->assertSessionHasErrors(['identity_confirmed', 'reason'])->assertSessionMissing('_old_input.reason')->assertSessionMissing('_old_input.review_token');
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.merge'));
    $this->postJson($this->pair.'/merge', $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('patients.merge');
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->postJson($this->pair.'/merge', $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save());
    $this->withSession(['auth.password_confirmed_at' => 0])->postJson($this->pair.'/merge', $this->input)->assertStatus(423);
});

it('rejects foreign bindings ownership injection unrelated survivor IDs and raw cross tenant foreign keys', function (): void {
    $this->postJson($this->base.'/patients/'.$this->survivor->public_id.'/compare/'.$this->foreign->public_id.'/merge', $this->input)->assertNotFound();
    $this->get($this->base.'/patients/'.$this->foreign->public_id.'/merges')->assertNotFound();
    $this->postJson($this->pair.'/merge', array_replace($this->input, ['survivor_id' => $this->foreign->id]))->assertUnprocessable();
    $this->postJson($this->pair.'/merge', $this->input + ['tenant_id' => $this->b['tenant']->id, 'merged_by' => $this->b['user']->id])->assertUnprocessable();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => DB::table('patient_merges')->insert(['tenant_id' => $this->a['tenant']->id, 'survivor_patient_id' => $this->survivor->id, 'merged_patient_id' => $this->foreign->id]))->toThrow(QueryException::class);
        expect(fn () => app(MergePatients::class)->execute($this->a['user'], $this->survivor, $this->foreign, $this->input))->toThrow(ValidationException::class);
    });
    expect(fn () => PatientMerge::query()->count())->toThrow(MissingTenant::class);
});

it('rolls back archiving decision and snapshots when audit persistence fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function (): void {
            expect(fn () => app(MergePatients::class)->execute($this->a['user'], $this->survivor, $this->source, $this->input))->toThrow(RuntimeException::class);
            expect(PatientMerge::query()->count())->toBe(0);
            expect($this->source->fresh())->not->toBeNull();
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});
