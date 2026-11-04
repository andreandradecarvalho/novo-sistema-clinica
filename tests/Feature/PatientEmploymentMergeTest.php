<?php

declare(strict_types=1);

use App\Domain\Companies\Actions\GrantCompanyAccess;
use App\Domain\Companies\Actions\SaveCompany;
use App\Domain\Companies\Enums\CompanyAccessScope;
use App\Domain\Companies\Services\CompanyAccessAuthorization;
use App\Domain\Occupational\Actions\CreateEmployment;
use App\Domain\Occupational\Actions\EndEmployment;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Actions\MergePatients;
use App\Domain\Patients\Actions\SavePatient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Patients\Services\PatientIdentityGroup;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
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
        $this->a['user']->givePermissionTo(['patients.view', 'patients.manage', 'patients.review', 'patients.merge', 'employments.view', 'employments.manage', 'companies.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->survivor = app(SavePatient::class)->execute(['name' => 'Pessoa sintética', 'birth_date' => '1990-01-02', 'cpf' => syntheticCpf()]);
        $this->source = app(SavePatient::class)->execute(['name' => 'Pessoa sintética', 'birth_date' => '1990-01-02']);
        $this->outsider = app(SavePatient::class)->execute(['name' => 'Outro paciente A']);
        $this->company = app(SaveCompany::class)->execute(['name' => 'Empregador raiz', 'is_active' => true]);
        $this->otherCompany = app(SaveCompany::class)->execute(['name' => 'Empregador da origem', 'is_active' => true]);
        $this->rootLink = app(CreateEmployment::class)->execute($this->survivor, $this->company, ['starts_on' => '2026-01-01', 'job_title' => 'Função raiz']);
        $this->sourceLink = app(CreateEmployment::class)->execute($this->source, $this->otherCompany, ['starts_on' => '2026-01-01', 'job_title' => 'Função preservada']);
        $this->outsiderLink = app(CreateEmployment::class)->execute($this->outsider, $this->company, ['starts_on' => '2026-01-01']);
    });
    $this->foreignLink = $this->context->run($this->b['tenant'], function () {
        $this->foreign = app(SavePatient::class)->execute(['name' => 'Paciente B']);
        $company = app(SaveCompany::class)->execute(['name' => 'Empregador B', 'is_active' => true]);

        return app(CreateEmployment::class)->execute($this->foreign, $company, ['starts_on' => '2026-01-01']);
    });
    $this->base = 'http://'.$this->a['host'];
    $this->pair = $this->base.'/patients/'.$this->survivor->public_id.'/compare/'.$this->source->public_id;
    $this->employmentsUrl = $this->base.'/patients/'.$this->survivor->public_id.'/employments';
    $this->postJson($this->base.'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson($this->base.'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
    $this->input = ['survivor_id' => $this->survivor->id, 'reason' => 'Identidade sintética conferida por fonte autorizada.', 'identity_confirmed' => '1',
        'review_token' => $this->get($this->pair)->assertOk()->viewData('reviewToken')];
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('consolidates employment reads without changing any historical row or employer', function (): void {
    $before = DB::table('patient_company_links')->orderBy('id')->get()->toJson();
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    expect(DB::table('patient_company_links')->orderBy('id')->get()->toJson())->toBe($before);
    $this->get($this->employmentsUrl)->assertOk()->assertSee('Função raiz')->assertSee('Função preservada')->assertSee('Empregador da origem')->assertSee('Origem preservada')->assertDontSee('Empregador B')
        ->assertViewHas('employments', fn ($links) => $links->total() === 2);
    $this->context->run($this->a['tenant'], function (): void {
        expect(app(PatientIdentityGroup::class)->memberIds($this->survivor))->toBe([(int) $this->survivor->id, (int) $this->source->id]);
        expect($this->survivor->employments()->count())->toBe(1);
        $merge = PatientMerge::query()->sole();
        expect($merge->payload_snapshot['version'])->toBe(2);
        $snapshot = json_decode(Crypt::decryptString($merge->payload_snapshot['source_encrypted']), true);
        expect((int) $snapshot['employments'][0]['patient_id'])->toBe((int) $this->source->id);
        expect((int) $snapshot['employments'][0]['company_id'])->toBe((int) $this->otherCompany->id);
    });
    $event = DB::table('activity_log')->where('event', 'merged_employments_listed')->sole();
    expect(json_decode($event->properties, true)['patient_ids'])->toBe([(int) $this->survivor->id, (int) $this->source->id]);
});

it('versions and terminates a consolidated link through the survivor while preserving original ownership', function (): void {
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    $this->get($this->employmentsUrl.'/'.$this->sourceLink->id.'/version')->assertOk();
    $this->post($this->employmentsUrl.'/'.$this->sourceLink->id.'/version', ['starts_on' => '2026-03-01', 'job_title' => 'Nova função'])->assertRedirect();
    $next = $this->context->run($this->a['tenant'], fn () => EmploymentLink::query()->where('patient_id', $this->source->id)->orderByDesc('id')->firstOrFail());
    expect((int) $next->company_id)->toBe((int) $this->otherCompany->id);
    $this->context->run($this->a['tenant'], function (): void {
        expect($this->sourceLink->fresh()->ends_on->toDateString())->toBe('2026-02-28');
        expect($this->sourceLink->fresh()->job_title)->toBe('Função preservada');
    });
    $this->post($this->employmentsUrl.'/'.$next->id.'/terminate', ['termination_date' => '2026-06-30'])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($next): void {
        expect($next->fresh()->termination_date->toDateString())->toBe('2026-06-30');
        expect((int) $next->fresh()->patient_id)->toBe((int) $this->source->id);
    });
    $this->get($this->base.'/patients/'.$this->source->public_id.'/employments')->assertNotFound();
    $this->post($this->employmentsUrl.'/register/'.$this->company->public_id, ['starts_on' => '2026-07-01', 'job_title' => 'Novo contrato'])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect((int) EmploymentLink::query()->where('job_title', 'Novo contrato')->sole()->patient_id)->toBe((int) $this->survivor->id));
});

it('rejects links from unrelated patients in the same clinic or another clinic after consolidation', function (): void {
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    foreach ([$this->outsiderLink, $this->foreignLink] as $link) {
        $this->get($this->employmentsUrl.'/'.$link->id.'/version')->assertNotFound();
        $this->postJson($this->employmentsUrl.'/'.$link->id.'/terminate', ['termination_date' => '2026-06-30'])->assertNotFound();
    }
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(EndEmployment::class)->execute($this->survivor, $this->outsiderLink, '2026-06-30'))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(CreateEmployment::class)->execute($this->source, $this->company, ['starts_on' => '2026-07-01']))->toThrow(ModelNotFoundException::class);
    });
    $this->context->run($this->b['tenant'], fn () => expect(fn () => app(PatientIdentityGroup::class)->memberIds($this->survivor))->toThrow(ModelNotFoundException::class));
    expect(fn () => app(PatientIdentityGroup::class)->memberIds($this->survivor))->toThrow(MissingTenant::class);
});

it('invalidates the reviewed decision when employment state changes without a patient edit', function (): void {
    $this->context->run($this->a['tenant'], fn () => app(EndEmployment::class)->execute($this->source, $this->sourceLink, '2026-06-30'));
    $this->postJson($this->pair.'/merge', $this->input)->assertUnprocessable()->assertJsonValidationErrors('review_token');
    $this->context->run($this->a['tenant'], function (): void {
        expect(PatientMerge::query()->count())->toBe(0);
        expect($this->source->fresh())->not->toBeNull();
    });
});

it('rechecks read and manage permissions on consolidated links', function (): void {
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('employments.manage'));
    $this->get($this->employmentsUrl)->assertOk();
    $this->postJson($this->employmentsUrl.'/'.$this->sourceLink->id.'/terminate', ['termination_date' => '2026-06-30'])->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('employments.view'));
    $this->get($this->employmentsUrl)->assertForbidden();
});

it('adds multiple independent origins without rewriting earlier merge decisions', function (): void {
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    $first = DB::table('patient_merges')->where('merged_patient_id', $this->source->id)->sole();
    $next = $this->context->run($this->a['tenant'], function () {
        $patient = app(SavePatient::class)->execute(['name' => 'Pessoa sintética']);
        app(CreateEmployment::class)->execute($patient, $this->otherCompany, ['starts_on' => '2026-02-01', 'job_title' => 'Outra origem']);

        return $patient;
    });
    $pair = $this->base.'/patients/'.$this->survivor->public_id.'/compare/'.$next->public_id;
    $input = array_replace($this->input, ['review_token' => $this->get($pair)->assertOk()->viewData('reviewToken')]);
    $this->post($pair.'/merge', $input)->assertRedirect();
    expect(DB::table('patient_merges')->where('id', $first->id)->sole())->toEqual($first);
    $this->get($this->employmentsUrl)->assertOk()->assertSee('Outra origem')->assertViewHas('employments', fn ($links) => $links->total() === 3);
});

it('keeps company grants independent of the merged patient identity', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('company_portal.status.view');
        app(GrantCompanyAccess::class)->execute($this->company, ['user_id' => $this->a['user']->id, 'scope' => 'occupational_status', 'valid_from' => '2026-01-01T09:00']);
    });
    $this->post($this->pair.'/merge', $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $authorization = app(CompanyAccessAuthorization::class);
        expect($authorization->allows($this->a['user'], $this->company, CompanyAccessScope::OccupationalStatus))->toBeTrue();
        expect($authorization->allows($this->a['user'], $this->otherCompany, CompanyAccessScope::OccupationalStatus))->toBeFalse();
    });
});

it('rolls back identity consolidation without changing links when audit persistence fails', function (): void {
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
            expect(app(PatientIdentityGroup::class)->memberIds($this->survivor))->toBe([(int) $this->survivor->id]);
            expect($this->source->fresh())->not->toBeNull();
            expect((int) $this->sourceLink->fresh()->patient_id)->toBe((int) $this->source->id);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});
