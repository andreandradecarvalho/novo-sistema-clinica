<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveExamPreparation;
use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
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
    [$this->examA, $this->otherA, $this->preparationA] = $this->context->run($this->a['tenant'], function (): array {
        $exam = ExamType::query()->create(['code' => 'SINT-A', 'name' => 'Exame sintético A']);
        $other = ExamType::query()->create(['code' => 'OTHER-A', 'name' => 'Outro exame A']);
        $preparation = $exam->preparations()->create(['title' => 'Preparo sintético A', 'instructions' => 'Texto sintético A']);

        return [$exam, $other, $preparation];
    });
    [$this->examB, $this->preparationB] = $this->context->run($this->b['tenant'], function (): array {
        $exam = ExamType::query()->create(['code' => 'SINT-B', 'name' => 'Exame sintético B']);

        return [$exam, $exam->preparations()->create(['title' => 'Preparo sintético B', 'instructions' => 'Texto sintético B'])];
    });
    $this->base = 'http://'.$this->a['host'].'/exam-types/'.$this->examA->public_id.'/preparations';
    $this->input = ['title' => 'Orientação sintética', 'instructions' => "Primeira linha sintética.\nSegunda linha sintética."];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates reads and edits textual preparations under the same exam with metadata only audit', function (): void {
    $this->get('http://'.$this->a['host'].'/exam-types/'.$this->examA->public_id)->assertOk()->assertSee('Preparos do exame');
    $this->get($this->base.'/create')->assertOk()->assertSee('Novo preparo');
    $this->post($this->base, $this->input)->assertRedirect();
    $preparation = $this->context->run($this->a['tenant'], fn () => ExamPreparation::query()->where('title', $this->input['title'])->sole());
    expect((int) $preparation->exam_type_id)->toBe((int) $this->examA->id);
    expect((int) $preparation->tenant_id)->toBe((int) $this->a['tenant']->id);
    $this->get($this->base.'/'.$preparation->id)->assertOk()->assertSee($this->input['instructions']);
    $this->get($this->base.'/'.$preparation->id.'/edit')->assertOk()->assertSee('Editar preparo');
    $this->put($this->base.'/'.$preparation->id, ['title' => 'Orientação corrigida', 'instructions' => 'Texto revisado sintético.'])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect($preparation->fresh()->instructions)->toBe('Texto revisado sintético.'));
    $events = DB::table('activity_log')->where('subject_type', ExamPreparation::class)->where('subject_id', $preparation->id)->get();
    expect($events->pluck('event')->all())->toBe(['exam_preparation_created', 'exam_preparation_viewed', 'exam_preparation_edit_viewed', 'exam_preparation_updated']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->deleteJson($this->base.'/'.$preparation->id)->assertStatus(405);
});

it('refuses preparations under the wrong exam in the same clinic and under a foreign clinic', function (): void {
    $wrong = 'http://'.$this->a['host'].'/exam-types/'.$this->otherA->public_id.'/preparations/'.$this->preparationA->id;
    $this->get($wrong)->assertNotFound();
    $this->get($wrong.'/edit')->assertNotFound();
    $this->putJson($wrong, $this->input)->assertNotFound();
    $this->get($this->base.'/'.$this->preparationB->id)->assertNotFound();
    $this->putJson($this->base.'/'.$this->preparationB->id, $this->input)->assertNotFound();
    $foreign = 'http://'.$this->a['host'].'/exam-types/'.$this->examB->public_id.'/preparations';
    $this->get($foreign)->assertNotFound();
    $this->postJson($foreign, $this->input)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(SaveExamPreparation::class)->execute($this->otherA, $this->input, $this->preparationA))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(SaveExamPreparation::class)->execute($this->examB, $this->input))->toThrow(ModelNotFoundException::class);
    });
});

it('rejects owner injection missing context reparenting and cross clinic foreign keys', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'exam_type_id' => $this->otherA->id, 'id' => $this->preparationB->id] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->putJson($this->base.'/'.$this->preparationA->id, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(fn () => ExamPreparation::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => $this->preparationA->fresh()->forceFill(['exam_type_id' => $this->otherA->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->preparationA->fresh()->forceFill(['tenant_id' => $this->b['tenant']->id])->save())->toThrow(LogicException::class);
        expect(fn () => DB::table('exam_type_preparations')->insert(['tenant_id' => $this->a['tenant']->id, 'exam_type_id' => $this->examB->id] + $this->input))->toThrow(QueryException::class);
        expect(fn () => $this->examA->delete())->toThrow(QueryException::class);
    });
});

it('requires catalogue read manage and second factor permissions independently', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.manage'));
    $this->get($this->base)->assertOk()->assertSee('Preparo sintético A')->assertDontSee('Preparo sintético B')->assertDontSee('Novo preparo');
    $this->get($this->base.'/'.$this->preparationA->id)->assertOk()->assertDontSee('Editar preparo');
    $this->get($this->base.'/create')->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->putJson($this->base.'/'.$this->preparationA->id, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.view'));
    $this->get($this->base)->assertForbidden();
    $this->get($this->base.'/'.$this->preparationA->id)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
});

it('validates required text and bounds that fit the utf8mb4 TEXT column', function (): void {
    foreach (['title' => ['', str_repeat('A', 151)], 'instructions' => ['', str_repeat('😀', 16001), ['invalid']]] as $field => $values) {
        foreach ($values as $value) {
            $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    expect(DB::table('exam_type_preparations')->count())->toBe(2);
    $text = str_repeat('😀', 16000);
    $this->post($this->base, ['instructions' => $text] + $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => expect(ExamPreparation::query()->where('title', $this->input['title'])->sole()->instructions)->toBe($text));
    $this->from($this->base.'/create')->post($this->base, ['title' => ['invalid'], 'instructions' => ['invalid']])->assertSessionHasErrors(['title', 'instructions']);
    $this->get($this->base.'/create')->assertOk();
});

it('paginates only the selected exam and escapes stored instructions and titles', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $this->preparationA->update(['title' => '<script>synthetic()</script>', 'instructions' => '<img src=x onerror=synthetic()>']);
        $this->otherA->preparations()->create(['title' => 'Não pertence ao exame', 'instructions' => 'Outro texto']);
        foreach (range(1, 20) as $number) {
            $this->examA->preparations()->create(['title' => 'Preparo paginado '.$number, 'instructions' => 'Texto fora da listagem']);
        }
    });
    $this->get($this->base)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertDontSee('<script>synthetic()</script>', false)->assertDontSee('Não pertence ao exame')->assertDontSee('Preparo sintético B')->assertDontSee('Texto fora da listagem')->assertSee('page=2', false);
    $this->get($this->base.'?page=2')->assertOk()->assertSee('Preparo paginado 20')->assertDontSee('Não pertence ao exame');
    $this->get($this->base.'/'.$this->preparationA->id)->assertOk()->assertSee('&lt;img src=x onerror=synthetic()&gt;', false)->assertDontSee('<img src=x onerror=synthetic()>', false);
    $this->get($this->base.'/'.$this->preparationA->id.'/edit')->assertOk()->assertDontSee('<img src=x onerror=synthetic()>', false);
});

it('rolls back preparation creation and update when audit persistence fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base, $this->input))->toThrow(RuntimeException::class);
        expect(DB::table('exam_type_preparations')->count())->toBe(2);
        expect(fn () => $this->put($this->base.'/'.$this->preparationA->id, $this->input))->toThrow(RuntimeException::class);
        expect(DB::table('exam_type_preparations')->where('id', $this->preparationA->id)->value('instructions'))->toBe('Texto sintético A');
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('rechecks action authorization and keeps inactive exam preparations available for catalogue administration', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->examA->update(['is_active' => false]));
    $this->get($this->base)->assertOk();
    $this->put($this->base.'/'.$this->preparationA->id, $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->revokePermissionTo('catalog.manage');
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(SaveExamPreparation::class)->execute($this->examA, $this->input))->toThrow(AuthorizationException::class);
        expect(fn () => app(SaveExamPreparation::class)->execute($this->examA, $this->input, $this->preparationA))->toThrow(AuthorizationException::class);
    });
});
