<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Actions\ManageProfessionalExamAuthorization;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Services\ProfessionalExamAccess;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
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
    foreach (['a', 'b'] as $key) {
        $clinic = $this->$key;
        $this->{'data'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic): array {
            $clinic['user']->givePermissionTo(['professionals.view', 'catalog.view', 'executions.authorizations.view', 'executions.authorizations.manage', 'clinical.access', 'executions.perform']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
            $professional = new Professional;
            $professional->forceFill(['user_id' => $clinic['user']->id])->save();
            $exam = ExamType::query()->create(['code' => 'GRANT-SYN', 'name' => 'Exame sintético autorizado', 'is_active' => true]);

            return compact('professional', 'exam');
        });
    }
    $this->url = 'http://'.$this->a['host'].'/professionals/'.$this->dataA['professional']->id.'/exam-authorizations';
    $this->input = ['exam_type_id' => $this->dataA['exam']->id, 'reason' => 'Validação sintética <script>synthetic()</script>'];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->postJson('http://'.$this->a['host'].'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('grants exactly one professional and exam without changing roles and preserves encrypted reasons', function (): void {
    $this->context->run($this->a['tenant'], fn () => expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataA['exam']))->toBeFalse());
    $this->get($this->url)->assertOk()->assertSee('Conceder exame');
    $this->post($this->url, $this->input)->assertRedirect($this->url);
    $this->postJson($this->url, $this->input)->assertUnprocessable();
    $this->get($this->url)->assertOk()->assertSee($this->input['reason'])->assertDontSee($this->input['reason'], false)->assertHeader('Cache-Control', 'no-store, private');
    $this->context->run($this->a['tenant'], function (): void {
        $grant = ProfessionalExamAuthorization::query()->sole();
        expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataA['exam']))->toBeTrue();
        $other = ExamType::query()->create(['code' => 'OTHER', 'name' => 'Outro exame sintético']);
        expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $other))->toBeFalse();
        expect($grant->reasonSnapshot()['grant'])->toBe($this->input['reason']);
        expect($grant->getRawOriginal('grant_reason_encrypted'))->not->toContain($this->input['reason']);
        expect($grant->toArray())->not->toHaveKey('grant_reason_encrypted');
        expect($this->a['user']->fresh()->roles)->toBeEmpty();
        expect(fn () => $grant->forceFill(['exam_type_id' => $other->id])->save())->toThrow(LogicException::class);
        expect(fn () => $grant->delete())->toThrow(LogicException::class);
    });
    expect(DB::table('activity_log')->where('log_name', 'professional_exam_authorizations')->pluck('properties')->filter())->toBeEmpty();
});

it('revokes immediately without cache and regrants as a new historical record', function (): void {
    $this->post($this->url, $this->input)->assertRedirect();
    $grant = $this->context->run($this->a['tenant'], fn () => ProfessionalExamAuthorization::query()->sole());
    $this->post($this->url.'/'.$grant->id.'/revoke', ['reason' => 'Revogação sintética'])->assertRedirect();
    $first = DB::table('professional_exam_authorizations')->where('id', $grant->id)->first();
    $this->post($this->url.'/'.$grant->id.'/revoke', ['reason' => 'Revogação sintética'])->assertRedirect();
    expect(DB::table('professional_exam_authorizations')->where('id', $grant->id)->first())->toEqual($first);
    $this->postJson($this->url.'/'.$grant->id.'/revoke', ['reason' => 'Outro motivo'])->assertUnprocessable();
    $this->context->run($this->a['tenant'], fn () => expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataA['exam']))->toBeFalse());
    $this->post($this->url, $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        expect(ProfessionalExamAuthorization::query()->count())->toBe(2)->and(ProfessionalExamAuthorization::query()->whereNull('revoked_at')->count())->toBe(1);
        expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataA['exam']))->toBeTrue();
    });
});

it('requires explicit clinical permissions active actor second factor and active exam on every check', function (): void {
    $this->post($this->url, $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $service = app(ProfessionalExamAccess::class);
        foreach (['clinical.access', 'executions.perform'] as $permission) {
            $this->a['user']->revokePermissionTo($permission);
            expect($service->allows($this->a['user'], $this->dataA['exam']))->toBeFalse();
            $this->a['user']->givePermissionTo($permission);
        }
        foreach ([['is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null]] as $change) {
            $this->a['user']->forceFill($change)->save();
            expect($service->allows($this->a['user'], $this->dataA['exam']))->toBeFalse();
        }
        $this->a['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->dataA['exam']->update(['is_active' => false]);
        expect($service->allows($this->a['user'], $this->dataA['exam']))->toBeFalse();
        $this->dataA['exam']->update(['is_active' => true]);
        ProfessionalExamAuthorization::query()->sole();
        DB::table('professional_exam_authorizations')->where('tenant_id', $this->a['tenant']->id)->update(['grant_reason_encrypted' => 'invalid']);
        expect($service->allows($this->a['user'], $this->dataA['exam']))->toBeFalse();
    });
});

it('scopes bindings and direct actions including grants to a different professional', function (): void {
    $foreign = 'http://'.$this->a['host'].'/professionals/'.$this->dataB['professional']->id.'/exam-authorizations';
    $this->postJson($foreign, $this->input)->assertNotFound();
    $this->postJson($this->url, ['exam_type_id' => $this->dataB['exam']->id] + $this->input)->assertNotFound();
    $foreignGrant = $this->context->run($this->b['tenant'], function () {
        auth()->setUser($this->b['user']->fresh());

        return app(ManageProfessionalExamAuthorization::class)->grant($this->dataB['professional'], $this->dataB['exam'], 'Sintético');
    });
    $this->postJson($this->url.'/'.$foreignGrant->id.'/revoke', ['reason' => 'Sintético'])->assertNotFound();
    $this->context->run($this->a['tenant'], function () use ($foreignGrant): void {
        expect(fn () => $foreignGrant->forceFill(['tenant_id' => $this->a['tenant']->id])->reasonSnapshot())->toThrow(LogicException::class);
    });
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(ManageProfessionalExamAuthorization::class)->grant($this->dataB['professional'], $this->dataA['exam'], 'Sintético'))->toThrow(ModelNotFoundException::class);
        expect(app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataB['exam']))->toBeFalse();
    });
    expect(fn () => app(ProfessionalExamAccess::class)->allows($this->a['user'], $this->dataA['exam']))->toThrow(MissingTenant::class);
});

it('separates read from management rejects authoritative inputs and rechecks inactive managers', function (): void {
    foreach (['reason' => '', 'professional_id' => $this->dataB['professional']->id, 'granted_by' => 1, 'revoked_at' => '2026-11-05', 'permissions' => ['executions.perform']] as $field => $value) {
        $this->postJson($this->url, [$field => $value] + $this->input)->assertUnprocessable();
    }
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('executions.authorizations.manage'));
    $this->get($this->url)->assertOk()->assertDontSee('Conceder exame');
    $this->postJson($this->url, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('executions.authorizations.manage');
        $this->a['user']->forceFill(['is_active' => false])->save();
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(ManageProfessionalExamAuthorization::class)->grant($this->dataA['professional'], $this->dataA['exam'], 'Sintético'))->toThrow(AuthorizationException::class);
    });
});

it('enforces composite FKs unique active pair and consistent revocation', function (): void {
    $this->post($this->url, $this->input)->assertRedirect();
    $row = DB::table('professional_exam_authorizations')->first();
    foreach (['professional_id' => $this->dataB['professional']->id, 'exam_type_id' => $this->dataB['exam']->id, 'granted_by' => $this->b['user']->id] as $field => $value) {
        expect(fn () => DB::table('professional_exam_authorizations')->where('id', $row->id)->update([$field => $value]))->toThrow(QueryException::class);
    }
    $copy = (array) $row;
    unset($copy['id'], $copy['active_flag']);
    expect(fn () => DB::table('professional_exam_authorizations')->insert($copy))->toThrow(QueryException::class);
    expect(fn () => DB::table('professional_exam_authorizations')->where('id', $row->id)->update(['revoked_at' => now()]))->toThrow(QueryException::class);
});

it('rolls back grants and revocations when audit fails', function (): void {
    $fail = new class
    {
        public function record(...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure.');
        }
    };
    app()->instance(RegistryAudit::class, $fail);
    try {
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(ManageProfessionalExamAuthorization::class)->grant($this->dataA['professional'], $this->dataA['exam'], 'Sintético'))->toThrow(RuntimeException::class);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    expect(DB::table('professional_exam_authorizations')->count())->toBe(0);
    $this->post($this->url, $this->input)->assertRedirect();
    $grant = $this->context->run($this->a['tenant'], fn () => ProfessionalExamAuthorization::query()->sole());
    app()->instance(RegistryAudit::class, $fail);
    try {
        $this->context->run($this->a['tenant'], function () use ($grant): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(ManageProfessionalExamAuthorization::class)->revoke($this->dataA['professional'], $grant, 'Sintético'))->toThrow(RuntimeException::class);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    expect(DB::table('professional_exam_authorizations')->where('id', $grant->id)->value('revoked_at'))->toBeNull();
});

it('requires recent password confirmation for grant and revocation', function (): void {
    $this->withSession(['auth.password_confirmed_at' => 0])->postJson($this->url, $this->input)->assertStatus(423);
    $this->postJson('http://'.$this->a['host'].'/user/confirm-password', ['password' => $this->a['password']])->assertSuccessful();
    $this->post($this->url, $this->input)->assertRedirect();
    $grant = $this->context->run($this->a['tenant'], fn () => ProfessionalExamAuthorization::query()->sole());
    $this->withSession(['auth.password_confirmed_at' => 0])->postJson($this->url.'/'.$grant->id.'/revoke', ['reason' => 'Sintético'])->assertStatus(423);
});
