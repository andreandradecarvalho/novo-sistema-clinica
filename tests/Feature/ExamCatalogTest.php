<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveExamType;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Actions\ProvisionTenant;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    foreach ([$this->a, $this->b] as $clinic) {
        $this->context->run($clinic['tenant'], function () use ($clinic): void {
            $clinic['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
        });
    }
    $this->examA = $this->context->run($this->a['tenant'], fn () => ExamType::query()->create(['code' => 'SINT-A', 'name' => 'Exame sintético A']));
    $this->examB = $this->context->run($this->b['tenant'], fn () => ExamType::query()->create(['code' => 'SINT-B', 'name' => 'Exame sintético B']));
    $this->input = ['code' => 'SINT-01', 'name' => 'Exame de teste', 'requires_fasting' => 0, 'default_duration_min' => 30, 'price' => '12.34', 'ordem' => 0, 'is_active' => 1];
    $this->base = 'http://'.$this->a['host'].'/exam-types';
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates and updates the catalogue with exact decimal prices and stable public identity', function (): void {
    $this->get($this->base.'/create')->assertOk()->assertSee('Novo exame');
    $this->post($this->base, ['code' => ' sint-01 '] + $this->input)->assertRedirect();
    $exam = $this->context->run($this->a['tenant'], fn () => ExamType::query()->where('code', 'SINT-01')->sole());
    $this->get($this->base.'/'.$exam->public_id)->assertOk()->assertSee('12.34');
    $this->get($this->base.'/'.$exam->public_id.'/edit')->assertOk()->assertSee('Editar exame');
    $this->put($this->base.'/'.$exam->public_id, ['price' => '0', 'is_active' => 0, 'requires_fasting' => 1] + $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($exam): void {
        $fresh = $exam->fresh();
        expect($fresh->price)->toBe('0.00');
        expect($fresh->is_active)->toBeFalse();
        expect($fresh->requires_fasting)->toBeTrue();
        expect($fresh->public_id)->toBe($exam->public_id);
        expect($fresh->scope)->toBe('exame');
    });
    $events = DB::table('activity_log')->where('log_name', 'catalog')->where('subject_id', $exam->id)->get();
    expect($events->pluck('event')->all())->toBe(['exam_type_created', 'exam_type_viewed', 'exam_type_edit_viewed', 'exam_type_updated']);
    expect($events->pluck('properties')->filter())->toBeEmpty();
    $this->deleteJson($this->base.'/'.$exam->public_id)->assertStatus(405);
});

it('enforces normalized code uniqueness in the database within each clinic', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $this->postJson($this->base, ['code' => 'sint-01'] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => DB::table('exam_types')->insert(['tenant_id' => $this->a['tenant']->id, 'public_id' => (string) Str::ulid(), 'code' => 'SINT-01', 'name' => 'Duplicado']))->toThrow(QueryException::class);
    });
    $this->postJson('http://'.$this->a['host'].'/logout')->assertSuccessful();
    $this->postJson('http://'.$this->b['host'].'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
    $this->post('http://'.$this->b['host'].'/exam-types', $this->input)->assertRedirect();
    expect(DB::table('exam_types')->where('code', 'SINT-01')->count())->toBe(2);
});

it('rejects foreign bindings ownership injection missing context and mutation of public identity', function (): void {
    $this->get($this->base.'/'.$this->examB->public_id)->assertNotFound();
    $this->get($this->base.'/'.$this->examB->public_id.'/edit')->assertNotFound();
    $this->putJson($this->base.'/'.$this->examB->public_id, $this->input)->assertNotFound();
    foreach (['tenant_id' => $this->b['tenant']->id, 'public_id' => (string) Str::ulid(), 'scope' => 'other', 'id' => $this->examB->id] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(fn () => ExamType::query()->count())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        expect(fn () => app(SaveExamType::class)->execute($this->input, $this->examB))->toThrow(ModelNotFoundException::class);
        expect(fn () => $this->examA->fresh()->forceFill(['tenant_id' => $this->b['tenant']->id])->save())->toThrow(LogicException::class);
        expect(fn () => $this->examA->fresh()->forceFill(['public_id' => (string) Str::ulid()])->save())->toThrow(LogicException::class);
    });
});

it('validates amounts duration flags and bounds before database writes', function (): void {
    foreach (['code' => [str_repeat('ß', 20)], 'price' => ['-1', '0.001', '1e3', '10000000000.00'], 'default_duration_min' => [0, -1, 65536, '1.5'], 'ordem' => [-1, 65536], 'requires_fasting' => ['yes', null], 'is_active' => ['yes']] as $field => $values) {
        foreach ($values as $value) {
            $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    $this->postJson($this->base, ['code' => str_repeat('A', 21), 'name' => str_repeat('A', 121)] + $this->input)->assertUnprocessable()->assertJsonValidationErrors(['code', 'name']);
    expect(DB::table('exam_types')->count())->toBe(2);
    $this->post($this->base, ['price' => null, 'default_duration_min' => null] + $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $exam = ExamType::query()->where('code', 'SINT-01')->sole();
        expect($exam->price)->toBeNull();
        expect($exam->default_duration_min)->toBeNull();
    });
});

it('requires independent catalogue permissions and a confirmed second factor', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.manage'));
    $this->get($this->base)->assertOk()->assertSee('Exame sintético A')->assertDontSee('Exame sintético B')->assertDontSee('Novo exame');
    $this->get($this->base.'/create')->assertForbidden();
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->putJson($this->base.'/'.$this->examA->public_id, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('catalog.view'));
    $this->get($this->base.'/'.$this->examA->public_id)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['catalog.view', 'catalog.manage']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->get($this->base)->assertForbidden();
});

it('rolls back a catalogue update when operational audit persistence fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->put($this->base.'/'.$this->examA->public_id, $this->input))->toThrow(RuntimeException::class);
        $stored = DB::table('exam_types')->where('tenant_id', $this->a['tenant']->id)->where('id', $this->examA->id)->first();
        expect($stored->code)->toBe('SINT-A');
        expect($stored->name)->toBe('Exame sintético A');
        expect(DB::table('activity_log')->where('log_name', 'catalog')->count())->toBe(0);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('paginates tenant catalogue and escapes untrusted names in list detail and form', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        $this->examA->update(['name' => '<script>synthetic()</script>', 'ordem' => 0]);
        foreach (range(1, 20) as $number) {
            ExamType::query()->create(['code' => 'PAGE-'.$number, 'name' => 'Paginado '.$number, 'ordem' => $number]);
        }
    });
    $this->get($this->base)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertDontSee('<script>synthetic()</script>', false)->assertSee('page=2', false)->assertDontSee('Exame sintético B');
    $this->get($this->base.'?page=2')->assertOk()->assertSee('Paginado 20')->assertDontSee('Exame sintético B');
    $this->get($this->base.'/'.$this->examA->public_id)->assertOk()->assertDontSee('<script>synthetic()</script>', false);
    $this->get($this->base.'/'.$this->examA->public_id.'/edit')->assertOk()->assertDontSee('<script>synthetic()</script>', false);
});

it('provisions the catalogue permissions idempotently without granting them to other roles', function (): void {
    foreach ([$this->a, $this->b] as $clinic) {
        foreach (range(1, 2) as $iteration) {
            app(ProvisionTenant::class)->execute(['slug' => $clinic['tenant']->slug, 'name' => $clinic['tenant']->name, 'domain' => $clinic['host']]);
        }
        $this->context->run($clinic['tenant'], function (): void {
            $admin = Role::findByName('clinic_admin', 'web');
            expect($admin->hasPermissionTo('catalog.view'))->toBeTrue();
            expect($admin->hasPermissionTo('catalog.manage'))->toBeTrue();
            foreach (['receptionist', 'doctor', 'technician', 'company_user'] as $name) {
                expect(Role::findByName($name, 'web')->hasPermissionTo('catalog.manage'))->toBeFalse();
            }
        });
    }
    expect(DB::table('permissions')->where('name', 'like', 'catalog.%')->count())->toBe(2);
});
