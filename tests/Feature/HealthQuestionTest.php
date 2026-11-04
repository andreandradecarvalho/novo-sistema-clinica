<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\CreateHealthQuestionGroup;
use App\Domain\Catalog\Actions\PublishHealthQuestion;
use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
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
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    [$this->groupA, $this->otherA, $this->questionA] = $this->context->run($this->a['tenant'], function (): array {
        $group = HealthQuestionGroup::query()->create(['code' => 'GROUP', 'name' => 'Grupo sintético A']);
        $other = HealthQuestionGroup::query()->create(['code' => 'OTHER', 'name' => 'Outro grupo A']);

        return [$group, $other, $group->questions()->create(['code' => 'QUESTION', 'label' => 'Enunciado sintético A'])];
    });
    [$this->groupB, $this->questionB] = $this->context->run($this->b['tenant'], function (): array {
        $group = HealthQuestionGroup::query()->create(['code' => 'GROUP', 'name' => 'Grupo sintético B']);

        return [$group, $group->questions()->create(['code' => 'QUESTION', 'label' => 'Enunciado sintético B'])];
    });
    $this->base = 'http://'.$this->a['host'].'/questionnaires';
    $this->groupUrl = $this->base.'/'.$this->groupA->id;
    $this->questionUrl = $this->groupUrl.'/questions/'.$this->questionA->id;
    $this->input = ['code' => 'NEW', 'label' => 'Nova pergunta sintética', 'ordem' => 4];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates groups and questions and preserves every prior version with metadata only audit', function (): void {
    $this->get($this->base)->assertOk()->assertSee('Grupo sintético A')->assertDontSee('Grupo sintético B');
    $this->post($this->base, ['code' => 'NEW', 'name' => 'Novo grupo sintético', 'ordem' => 2])->assertRedirect();
    $this->post($this->groupUrl.'/questions', $this->input)->assertRedirect();
    $this->post($this->questionUrl.'/versions', ['label' => 'Enunciado revisado sintético'])->assertRedirect();
    $latest = $this->context->run($this->a['tenant'], fn () => HealthQuestion::query()->where('code', 'QUESTION')->orderByDesc('version')->firstOrFail());
    expect($latest->version)->toBe(2)->and($latest->type)->toBe('text')->and($latest->options)->toBeNull()->and($latest->applies_to)->toBe('all');
    expect(DB::table('health_questions')->where('id', $this->questionA->id)->value('label'))->toBe('Enunciado sintético A');
    $this->get($this->groupUrl)->assertOk()->assertSee('Enunciado sintético A')->assertSee('Enunciado revisado sintético')->assertDontSee('Enunciado sintético B');
    $this->get($this->questionUrl)->assertOk()->assertSee('Enunciado sintético A');
    $this->get($this->groupUrl.'/questions/'.$latest->id)->assertOk()->assertSee('Versão 2');
    $events = DB::table('activity_log')->where('subject_type', HealthQuestion::class)->get();
    expect($events->pluck('event')->all())->toContain('health_question_version_created', 'health_question_version_viewed');
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->putJson($this->questionUrl, ['label' => 'Sobrescrita'])->assertStatus(405);
    $this->deleteJson($this->questionUrl)->assertStatus(405);
});

it('rejects stale revisions duplicate codes and preserves tenant scoped uniqueness', function (): void {
    $this->post($this->questionUrl.'/versions', ['label' => 'Versão dois'])->assertRedirect();
    $this->postJson($this->questionUrl.'/versions', ['label' => 'Versão concorrente obsoleta'])->assertUnprocessable()->assertJsonValidationErrors('label');
    $this->postJson($this->base, ['code' => 'GROUP', 'name' => 'Duplicado', 'ordem' => 0])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson($this->base.'/'.$this->otherA->id.'/questions', ['code' => 'QUESTION'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(DB::table('health_questions')->where('tenant_id', $this->a['tenant']->id)->where('code', 'QUESTION')->count())->toBe(2);
});

it('refuses foreign tenant and wrong group route bindings and action sources', function (): void {
    $this->get($this->base.'/'.$this->groupB->id)->assertNotFound();
    $this->postJson($this->base.'/'.$this->groupB->id.'/questions', $this->input)->assertNotFound();
    foreach ([$this->base.'/'.$this->otherA->id.'/questions/'.$this->questionA->id, $this->groupUrl.'/questions/'.$this->questionB->id] as $url) {
        $this->get($url)->assertNotFound();
        $this->postJson($url.'/versions', ['label' => 'Intrusão'])->assertNotFound();
    }
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(PublishHealthQuestion::class)->execute($this->otherA, ['label' => 'Intrusão'], $this->questionA))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(PublishHealthQuestion::class)->execute($this->groupB, $this->input))->toThrow(ModelNotFoundException::class);
    });
});

it('requires read management and second factor and rechecks action authorization', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Novo grupo');
    $this->get($this->questionUrl)->assertOk()->assertDontSee('Cadastrar nova versão');
    $this->postJson($this->groupUrl.'/questions', $this->input)->assertForbidden();
    $this->postJson($this->questionUrl.'/versions', ['label' => 'Intrusão'])->assertForbidden();
    $this->postJson($this->base, ['code' => 'NEW', 'name' => 'Novo', 'ordem' => 0])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(PublishHealthQuestion::class)->execute($this->groupA, $this->input))->toThrow(AuthorizationException::class);
        expect(fn () => app(CreateHealthQuestionGroup::class)->execute(['code' => 'NEW', 'name' => 'Novo', 'ordem' => 0]))->toThrow(AuthorizationException::class);
        $this->a['user']->revokePermissionTo('catalog.view');
    });
    $this->get($this->base)->assertForbidden();
    $this->get($this->groupUrl)->assertForbidden();
    $this->get($this->questionUrl)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
});

it('rejects injected identity version type and applicability fields', function (): void {
    foreach (['tenant_id' => 1, 'id' => 1, 'group_id' => 1, 'version' => 99, 'type' => 'choice', 'options' => ['a'], 'applies_to' => 'female', 'is_active' => false] as $field => $value) {
        $this->postJson($this->groupUrl.'/questions', [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    foreach (['code' => 'ALTERED', 'ordem' => 2] as $field => $value) {
        $this->postJson($this->questionUrl.'/versions', [$field => $value, 'label' => 'Novo'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    foreach (['tenant_id', 'id'] as $field) {
        $this->postJson($this->base, [$field => 1, 'code' => 'NEW', 'name' => 'Novo', 'ordem' => 0])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

it('enforces immutable models missing context and composite foreign keys', function (): void {
    expect(fn () => HealthQuestion::query()->count())->toThrow(MissingTenant::class);
    expect(fn () => HealthQuestionGroup::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->questionA->fresh()->forceFill(['label' => 'Sobrescrita'])->save())->toThrow(LogicException::class);
        expect(fn () => $this->questionA->fresh()->forceFill(['group_id' => $this->otherA->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->questionA->fresh()->delete())->toThrow(LogicException::class);
        expect(fn () => $this->groupA->fresh()->update(['name' => 'Outro nome']))->toThrow(LogicException::class);
        expect(fn () => $this->groupA->fresh()->delete())->toThrow(LogicException::class);
        expect(fn () => DB::table('health_questions')->insert(['tenant_id' => $this->a['tenant']->id, 'group_id' => $this->groupB->id, 'code' => 'FOREIGN', 'label' => 'Inválido']))->toThrow(QueryException::class);
        expect(fn () => DB::table('health_question_groups')->where('id', $this->groupA->id)->delete())->toThrow(QueryException::class);
    });
});

it('validates field limits and escapes html including malformed old input', function (): void {
    foreach (['code' => ['', str_repeat('A', 61), 'bad code'], 'label' => ['', str_repeat('A', 256), ['invalid']], 'ordem' => [-1, 65536, 'x']] as $field => $values) {
        foreach ($values as $value) {
            $this->postJson($this->groupUrl.'/questions', [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    $this->from($this->groupUrl)->post($this->groupUrl.'/questions', ['label' => ['invalid'], 'code' => ['invalid'], 'ordem' => ['invalid']])->assertSessionHasErrors(['label', 'code', 'ordem']);
    $this->get($this->groupUrl)->assertOk();
    $this->post($this->groupUrl.'/questions', ['label' => '<script>synthetic()</script>'] + $this->input)->assertRedirect();
    $this->get($this->groupUrl)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertDontSee('<script>synthetic()</script>', false);
    foreach (['code' => str_repeat('A', 41), 'name' => str_repeat('A', 121)] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + ['code' => 'NEW', 'name' => 'Novo', 'ordem' => 0])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

it('rolls back question versions and groups when audit persistence fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->groupUrl.'/questions', $this->input))->toThrow(RuntimeException::class);
        expect(fn () => $this->post($this->questionUrl.'/versions', ['label' => 'Revisão']))->toThrow(RuntimeException::class);
        expect(fn () => $this->post($this->base, ['code' => 'NEW', 'name' => 'Novo', 'ordem' => 0]))->toThrow(RuntimeException::class);
        expect(DB::table('health_questions')->count())->toBe(2);
        expect(DB::table('health_question_groups')->count())->toBe(3);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('refuses version overflow and unsupported imported question configurations', function (): void {
    foreach ([['version' => 65535], ['type' => 'choice', 'options' => '["Synthetic"]'], ['applies_to' => 'restricted']] as $attributes) {
        DB::table('health_questions')->where('id', $this->questionA->id)->update($attributes + ['version' => 1, 'type' => 'text', 'options' => null, 'applies_to' => 'all']);
        $this->postJson($this->questionUrl.'/versions', ['label' => 'Não deve converter'])->assertUnprocessable()->assertJsonValidationErrors('label');
    }
    expect(DB::table('health_questions')->count())->toBe(2);
});

it('paginates groups and question histories without disclosing other clinics', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        foreach (range(1, 21) as $number) {
            HealthQuestionGroup::query()->create(['code' => 'PAGE-'.$number, 'name' => 'Grupo paginado '.$number]);
            $this->groupA->questions()->create(['code' => 'PAGE-'.$number, 'label' => 'Pergunta paginada '.$number]);
        }
    });
    $this->get($this->base)->assertOk()->assertSee('page=2', false)->assertDontSee('Grupo sintético B');
    $this->get($this->base.'?page=2')->assertOk()->assertSee('Grupo paginado 21')->assertDontSee('Grupo sintético B');
    $this->get($this->groupUrl)->assertOk()->assertSee('page=2', false)->assertDontSee('Enunciado sintético B');
    $this->get($this->groupUrl.'?page=2')->assertOk()->assertSee('Enunciado sintético A')->assertDontSee('Enunciado sintético B');
});
