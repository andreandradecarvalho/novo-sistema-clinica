<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\ReadExamOrder;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Models\ExamOrderItem;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Actions\ProvisionTenant;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    foreach (['a', 'b'] as $key) {
        $clinic = $this->$key;
        $this->{'data'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic): array {
            $clinic['user']->givePermissionTo(['patients.view', 'orders.view', 'orders.manage', 'catalog.view']);
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
            $patient = Patient::query()->create(['name' => 'Paciente sintético']);
            $exam = ExamType::query()->create(['code' => 'SYN-1', 'name' => 'Exame sintético', 'price' => '12.34']);
            $other = ExamType::query()->create(['code' => 'SYN-2', 'name' => 'Outro exame sintético', 'price' => '0.00']);

            return compact('patient', 'exam', 'other');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders';
    $this->input = ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => [$this->dataA['exam']->id, $this->dataA['other']->id]];
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a multi exam draft without appointment and preserves exact historical prices and descriptions', function (): void {
    $this->get($this->base.'/create')->assertOk()->assertSee('Novo pedido de exames');
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $this->context->run($this->a['tenant'], function () use ($order): void {
        expect($order->status)->toBe('draft')->and($order->payer_type)->toBe('patient')->and($order->authorized_at)->toBeNull();
        expect($order->company_id)->toBeNull()->and($order->requested_by)->toBeNull();
        expect($order->context_encrypted['payer_name'])->toBe('Paciente sintético');
        expect($order->getRawOriginal('context_encrypted'))->not->toContain('Paciente sintético');
        expect($order->toArray())->not->toHaveKey('context_encrypted');
        expect($order->items()->orderBy('id')->pluck('unit_price')->all())->toBe(['12.34', '0.00']);
        expect($order->items()->pluck('status')->unique()->all())->toBe(['draft']);
        $this->dataA['exam']->update(['price' => '99.99', 'name' => 'Nome novo', 'is_active' => false]);
    });
    $this->get($this->base.'/'.$order->public_id)->assertOk()->assertSee('12.34')->assertSee('Exame sintético')->assertDontSee('99.99')->assertDontSee('Nome novo')->assertHeader('Cache-Control', 'no-store, private');
    expect(DB::table('activity_log')->where('log_name', 'orders')->pluck('properties')->filter())->toBeEmpty();
    expect(DB::table('activity_log')->where('event', 'exam_order_created')->value('causer_id'))->toBe($this->a['user']->id);
});

it('rejects duplicate missing malformed foreign inactive and unpriced exams without partial writes', function (): void {
    foreach ([[], [$this->dataA['exam']->id, $this->dataA['exam']->id], [$this->dataB['exam']->id], ['x'], [0], range(100, 120), ['named' => $this->dataA['exam']->id]] as $ids) {
        $this->postJson($this->base, ['idempotency_key' => $this->input['idempotency_key'], 'exam_ids' => $ids])->assertUnprocessable();
    }
    foreach ([['is_active' => false], ['is_active' => true, 'price' => null]] as $input) {
        $this->context->run($this->a['tenant'], fn () => $this->dataA['exam']->update($input));
        $this->postJson($this->base, $this->input)->assertUnprocessable()->assertJsonValidationErrors('exam_ids');
    }
    expect(DB::table('exam_orders')->count())->toBe(0)->and(DB::table('exam_order_items')->count())->toBe(0);
});

it('refuses tenant patient amount state and snapshot injection from the client', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'patient_id' => $this->dataB['patient']->id, 'id' => 55, 'public_id' => 'fake', 'unit_price' => '0.01', 'discount_amount' => '100', 'status' => 'authorized', 'authorized_at' => now()->toDateTimeString(), 'pricing_snapshot' => ['source' => 'fake'], 'context_encrypted' => 'fake', 'payer_type' => 'company', 'company_id' => 2, 'company_unit_id' => 3, 'requested_by' => 4, 'items' => [['unit_price' => 0]]] as $field => $value) {
        $this->postJson($this->base, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(DB::table('exam_orders')->count())->toBe(0);
});

it('refuses foreign or wrong patient bindings and action models even with known order ids', function (): void {
    $orderB = $this->context->run($this->b['tenant'], function (): ExamOrder {
        auth()->setUser($this->b['user']->fresh());

        return app(CreateExamOrder::class)->execute($this->dataB['patient'], [$this->dataB['exam']->id], (string) Str::uuid());
    });
    $this->get($this->base.'/'.$orderB->public_id)->assertNotFound();
    $foreignBase = 'http://'.$this->a['host'].'/patients/'.$this->dataB['patient']->public_id.'/orders';
    $this->postJson($foreignBase, $this->input)->assertNotFound();
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $another = $this->context->run($this->a['tenant'], fn () => Patient::query()->create(['name' => 'Outro paciente']));
    $this->get('http://'.$this->a['host'].'/patients/'.$another->public_id.'/orders/'.$order->public_id)->assertNotFound();
    $this->context->run($this->a['tenant'], function () use ($orderB): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateExamOrder::class)->execute($this->dataB['patient'], [$this->dataA['exam']->id], (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
        expect(fn () => app(ReadExamOrder::class)->execute($this->dataA['patient'], $orderB))->toThrow(ModelNotFoundException::class);
    });
});

it('separates order read management patient catalogue and confirmed second factor permissions', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('orders.manage'));
    $this->get($this->base)->assertOk()->assertDontSee('Novo pedido');
    $this->postJson($this->base, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('orders.manage');
        $this->a['user']->revokePermissionTo('catalog.view');
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id], (string) Str::uuid()))->toThrow(AuthorizationException::class);
    });
    $this->get($this->base.'/create')->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('patients.view'));
    $this->get($this->base)->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo(['patients.view', 'catalog.view']);
        $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
    });
    $this->postJson($this->base, $this->input)->assertForbidden();
});

it('rolls back order items and creation audit when audit persistence fails', function (): void {
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
        expect(DB::table('exam_orders')->count())->toBe(0)->and(DB::table('exam_order_items')->count())->toBe(0);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('requires tenant context and enforces immutable order and item instances', function (): void {
    expect(fn () => ExamOrder::query()->count())->toThrow(MissingTenant::class);
    expect(fn () => ExamOrderItem::query()->count())->toThrow(MissingTenant::class);
    $this->post($this->base, $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $order = ExamOrder::query()->sole();
        $item = $order->items()->firstOrFail();
        expect(fn () => $order->forceFill(['status' => 'authorized'])->save())->toThrow(LogicException::class);
        expect(fn () => $item->forceFill(['unit_price' => '0.00'])->save())->toThrow(LogicException::class);
        expect(fn () => $item->delete())->toThrow(LogicException::class);
        expect(fn () => $order->delete())->toThrow(LogicException::class);
    });
    $orderId = DB::table('exam_orders')->value('public_id');
    $this->putJson($this->base.'/'.$orderId, [])->assertStatus(405);
    $this->deleteJson($this->base.'/'.$orderId)->assertStatus(405);
});

it('enforces composite order patient and exam foreign keys and nonnegative amounts at database level', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $orderId = DB::table('exam_orders')->value('id');
    expect(fn () => DB::table('exam_orders')->where('id', $orderId)->update(['patient_id' => $this->dataB['patient']->id]))->toThrow(QueryException::class);
    $item = ['tenant_id' => $this->a['tenant']->id, 'exam_order_id' => $orderId, 'exam_type_id' => $this->dataA['exam']->id, 'quantity' => 1, 'unit_price' => '12.34', 'description_snapshot' => 'Sintético'];
    expect(fn () => DB::table('exam_order_items')->insert(['exam_type_id' => $this->dataB['exam']->id] + $item))->toThrow(QueryException::class);
    expect(fn () => DB::table('exam_order_items')->insert(['unit_price' => '-0.01'] + $item))->toThrow(QueryException::class);
    expect(fn () => DB::table('exam_order_items')->insert(['discount_amount' => '12.35'] + $item))->toThrow(QueryException::class);
});

it('refuses archived patients and direct invalid item lists before writing', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateExamOrder::class)->execute($this->dataA['patient'], [], (string) Str::uuid()))->toThrow(ValidationException::class);
        $this->dataA['patient']->delete();
        expect(fn () => app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id], (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    });
    $this->postJson($this->base, $this->input)->assertNotFound();
});

it('paginates only the selected patients orders and escapes historical descriptions', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->dataA['exam']->update(['name' => '<script>synthetic()</script>']));
    $this->post($this->base, $this->input)->assertRedirect();
    $id = DB::table('exam_orders')->value('public_id');
    $this->get($this->base.'/'.$id)->assertOk()->assertSee('&lt;script&gt;synthetic()&lt;/script&gt;', false)->assertDontSee('<script>synthetic()</script>', false);
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        foreach (range(1, 20) as $number) {
            app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['other']->id], (string) Str::uuid());
        }
    });
    $this->get($this->base)->assertOk()->assertSee('page=2', false);
    $this->get($this->base.'?page=2')->assertOk()->assertSee($id);
});

it('provisions order permissions idempotently without granting them to clinical or company roles', function (): void {
    foreach (range(1, 2) as $iteration) {
        app(ProvisionTenant::class)->execute(['slug' => $this->a['tenant']->slug, 'name' => $this->a['tenant']->name, 'domain' => $this->a['host']]);
    }
    $this->context->run($this->a['tenant'], function (): void {
        expect(Role::findByName('clinic_admin', 'web')->hasPermissionTo('orders.manage'))->toBeTrue();
        foreach (['receptionist', 'doctor', 'technician', 'company_user'] as $role) {
            expect(Role::findByName($role, 'web')->hasPermissionTo('orders.manage'))->toBeFalse();
        }
    });
    expect(DB::table('permissions')->where('name', 'like', 'orders.%')->count())->toBe(2);
});

it('authorizes once and cancels with encrypted reason while preserving items prices and authorization history', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $path = $this->base.'/'.$order->public_id;
    $this->get($path)->assertOk()->assertSee('Autorizar pedido')->assertSee('Cancelar pedido');
    $this->post($path.'/authorize', ['expected_status' => 'draft'])->assertRedirect();
    $authorizedAt = DB::table('exam_orders')->where('id', $order->id)->value('authorized_at');
    expect($authorizedAt)->not->toBeNull();
    expect(DB::table('exam_order_items')->where('exam_order_id', $order->id)->pluck('status')->unique()->all())->toBe(['authorized']);
    $this->postJson($path.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
    $reason = 'Motivo operacional sintético <script>bad()</script>';
    $this->post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => $reason])->assertRedirect();
    $this->context->run($this->a['tenant'], function () use ($order, $reason): void {
        $fresh = $order->fresh();
        expect($fresh->status)->toBe('canceled');
        expect($fresh->contextSnapshot()['cancellation']['reason'])->toBe($reason);
        expect($fresh->contextSnapshot()['authorization']['actor_id'])->toBe($this->a['user']->id);
        expect($fresh->getRawOriginal('context_encrypted'))->not->toContain($reason);
        expect($fresh->items()->orderBy('id')->pluck('unit_price')->all())->toBe(['12.34', '0.00']);
        expect($fresh->items()->pluck('status')->unique()->all())->toBe(['canceled']);
    });
    expect(DB::table('exam_orders')->where('id', $order->id)->value('authorized_at'))->toBe($authorizedAt);
    $this->get($path)->assertOk()->assertSee('Cancelado')->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false)->assertDontSee('Autorizar pedido')->assertDontSee('Cancelar pedido');
    $this->postJson($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Repetido'])->assertUnprocessable();
    expect(DB::table('activity_log')->where('event', 'exam_order_authorized')->count())->toBe(1);
    expect(DB::table('activity_log')->where('event', 'exam_order_canceled')->count())->toBe(1);
    expect(DB::table('activity_log')->where('log_name', 'orders')->pluck('properties')->filter())->toBeEmpty();
});

it('cancels drafts without authorizing them and refuses stale expected state', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $path = $this->base.'/'.$order->public_id;
    $this->postJson($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
    $this->post($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertRedirect();
    expect(DB::table('exam_orders')->where('id', $order->id)->value('authorized_at'))->toBeNull();
    $this->postJson($path.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
});

it('requires a reason and refuses client status prices ownership and actor in transitions without flashing the reason', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $id = DB::table('exam_orders')->value('public_id');
    $path = $this->base.'/'.$id;
    foreach (['', ' ', str_repeat('x', 1001), ['unexpected']] as $reason) {
        $this->postJson($path.'/cancel', ['expected_status' => 'draft', 'reason' => $reason])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }
    foreach (['actor_id' => 99, 'status' => 'completed', 'tenant_id' => $this->b['tenant']->id, 'patient_id' => $this->dataB['patient']->id, 'unit_price' => '0.01', 'items' => ['fake'], 'pricing_snapshot' => ['fake'], 'context_encrypted' => 'fake', 'authorized_at' => now()->toDateTimeString()] as $field => $value) {
        $this->postJson($path.'/cancel', [$field => $value, 'expected_status' => 'draft', 'reason' => 'Sintético'])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson($path.'/authorize', ['expected_status' => 'draft', 'reason' => 'Não permitido'])->assertUnprocessable();
    $this->from($path)->post($path.'/cancel', ['expected_status' => 'invalid', 'reason' => 'Texto confidencial sintético'])->assertSessionHasErrors('expected_status')->assertSessionMissing('_old_input.reason');
});

it('rechecks permissions active user and second factor on transitions without requiring catalog to cancel', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $path = $this->base.'/'.$order->public_id;
    $this->context->run($this->a['tenant'], function () use ($order): void {
        $this->a['user']->revokePermissionTo('orders.manage');
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(TransitionExamOrder::class)->execute($this->dataA['patient'], $order, OrderTransition::Authorize, 'draft'))->toThrow(AuthorizationException::class);
    });
    $this->postJson($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertForbidden();
    $this->context->run($this->a['tenant'], function (): void {
        $this->a['user']->givePermissionTo('orders.manage');
        $this->a['user']->revokePermissionTo('catalog.view');
    });
    $this->postJson($path.'/authorize', ['expected_status' => 'draft'])->assertForbidden();
    $this->post($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertRedirect();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save());
    $this->postJson($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertForbidden();
});

it('refuses crossed transition paths and direct foreign orders', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
    $foreign = 'http://'.$this->b['host'].'/patients/'.$this->dataB['patient']->public_id.'/orders/'.$order->public_id;
    $this->postJson('http://'.$this->a['host'].'/logout')->assertSuccessful();
    $this->postJson('http://'.$this->b['host'].'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
    $this->postJson($foreign.'/authorize', ['expected_status' => 'draft'])->assertNotFound();
    $this->postJson($foreign.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertNotFound();
    $this->context->run($this->b['tenant'], function () use ($order): void {
        auth()->setUser($this->b['user']->fresh());
        expect(fn () => app(TransitionExamOrder::class)->execute($this->dataB['patient'], $order, OrderTransition::Cancel, 'draft', 'Sintético'))->toThrow(ModelNotFoundException::class);
        expect(fn () => $order->contextSnapshot())->toThrow(LogicException::class);
    });
});

it('rolls back order state items evidence and timestamps if transition audit fails', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = DB::table('exam_orders')->first();
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic transition audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base.'/'.$order->public_id.'/authorize', ['expected_status' => 'draft']))->toThrow(RuntimeException::class);
        $fresh = DB::table('exam_orders')->where('id', $order->id)->first();
        expect($fresh->status)->toBe('draft')->and($fresh->authorized_at)->toBeNull()->and($fresh->context_encrypted)->toBe($order->context_encrypted);
        expect(DB::table('exam_order_items')->where('exam_order_id', $order->id)->pluck('status')->unique()->all())->toBe(['draft']);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
});

it('refuses unavailable exams without repricing and permits cancellation despite catalog inactivation', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $id = DB::table('exam_orders')->value('public_id');
    $this->context->run($this->a['tenant'], fn () => $this->dataA['exam']->update(['is_active' => false, 'price' => '99.99']));
    $this->postJson($this->base.'/'.$id.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
    $this->post($this->base.'/'.$id.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertRedirect();
    expect(DB::table('exam_order_items')->where('exam_type_id', $this->dataA['exam']->id)->value('unit_price'))->toBe('12.34');
});

it('fails closed on inconsistent items unsupported order states or corrupt encrypted evidence', function (): void {
    $this->post($this->base, $this->input)->assertRedirect();
    $order = DB::table('exam_orders')->first();
    $path = $this->base.'/'.$order->public_id;
    DB::table('exam_order_items')->where('exam_order_id', $order->id)->update(['status' => 'completed']);
    $this->postJson($path.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
    $this->postJson($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertUnprocessable();
    DB::table('exam_order_items')->where('exam_order_id', $order->id)->update(['status' => 'draft']);
    foreach (['in_progress', 'completed', 'canceled'] as $status) {
        DB::table('exam_orders')->where('id', $order->id)->update(['status' => $status]);
        $this->postJson($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
    }
    DB::table('exam_orders')->where('id', $order->id)->update(['status' => 'draft', 'context_encrypted' => 'corrupted']);
    $this->postJson($path.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable()->assertJsonValidationErrors('transition');
    expect(DB::table('exam_orders')->where('id', $order->id)->value('status'))->toBe('draft');
});

describe('company payers', function (): void {
    beforeEach(function (): void {
        foreach (['a', 'b'] as $key) {
            $clinic = $this->$key;
            $this->{'company'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic, $key) {
                $clinic['user']->givePermissionTo('companies.view');

                return Company::query()->create(['name' => 'Empresa sintética '.$key, 'is_active' => true]);
            });
        }
        $this->companyBase = $this->base.'/company/'.$this->companyA->public_id;
    });

    it('creates company payer orders with encrypted historic identity without changing the patients employer or granting portal access', function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->companyA->forceFill(['cnpj' => '12ABC34501DE35'])->save());
        $this->get($this->base.'/payers')->assertOk()->assertSee('Empresa sintética a')->assertDontSee('Empresa sintética b');
        $this->get($this->companyBase.'/create')->assertOk()->assertSee('Exames do pedido empresarial');
        $this->post($this->companyBase, $this->input)->assertRedirect();
        $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
        $this->context->run($this->a['tenant'], function () use ($order): void {
            expect($order->payer_type)->toBe('company')->and((int) $order->company_id)->toBe($this->companyA->id);
            expect($order->company_unit_id)->toBeNull()->and($order->contextSnapshot()['payer_name'])->toBe('Empresa sintética a');
            expect($order->contextSnapshot()['company_id'])->toBe($this->companyA->id);
            expect($order->contextSnapshot()['payer_cnpj'])->toBe('12ABC34501DE35');
            expect($order->getRawOriginal('context_encrypted'))->not->toContain('Empresa sintética a');
            expect($order->pricing_snapshot)->toBe(['schema_version' => 1, 'source' => 'catalog', 'payer_type' => 'company']);
            expect($this->dataA['patient']->fresh()->company_id)->toBeNull();
            $this->companyA->forceFill(['name' => 'Nome empresarial alterado', 'cnpj' => null])->save();
            $this->dataA['exam']->update(['price' => '88.88']);
        });
        $this->get($this->base.'/'.$order->public_id)->assertOk()->assertSee('Empresa sintética a')->assertSee('12ABC34501DE35')->assertDontSee('Nome empresarial alterado')->assertSee('12.34')->assertDontSee('88.88');
        $this->post($this->base.'/'.$order->public_id.'/authorize', ['expected_status' => 'draft'])->assertRedirect();
        expect(DB::table('company_user_access')->count())->toBe(0);
    });

    it('refuses inactive companies at creation and authorization but allows cancellation and historic reading', function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->companyA->update(['is_active' => false]));
        $this->get($this->companyBase.'/create')->assertNotFound();
        $this->postJson($this->companyBase, $this->input)->assertUnprocessable()->assertJsonValidationErrors('payer');
        $this->context->run($this->a['tenant'], fn () => $this->companyA->update(['is_active' => true]));
        $this->post($this->companyBase, $this->input)->assertRedirect();
        $id = DB::table('exam_orders')->value('public_id');
        $this->context->run($this->a['tenant'], fn () => $this->companyA->delete());
        $this->get($this->base.'/'.$id)->assertOk()->assertSee('Empresa sintética a');
        $this->postJson($this->base.'/'.$id.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
        $this->post($this->base.'/'.$id.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertRedirect();
    });

    it('requires company visibility for selection creation consultation and transitions without changing particular access', function (): void {
        $this->post($this->companyBase, $this->input)->assertRedirect();
        $id = DB::table('exam_orders')->value('public_id');
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.view'));
        $this->get($this->base.'/payers')->assertForbidden();
        $this->postJson($this->companyBase, $this->input)->assertForbidden();
        $this->get($this->base.'/'.$id)->assertForbidden();
        $this->get($this->base)->assertOk()->assertDontSee($id)->assertDontSee('Novo pedido empresarial');
        $this->postJson($this->base.'/'.$id.'/authorize', ['expected_status' => 'draft'])->assertForbidden();
        $this->get($this->base.'/create')->assertOk();
    });

    it('refuses foreign company route bindings and action instances and forbids payer snapshot injection', function (): void {
        $foreign = $this->base.'/company/'.$this->companyB->public_id;
        $this->get($foreign.'/create')->assertNotFound();
        $this->postJson($foreign, $this->input)->assertNotFound();
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(CreateExamOrder::class)->execute($this->dataA['patient'], $this->input['exam_ids'], (string) Str::uuid(), $this->companyB))->toThrow(ModelNotFoundException::class);
        });
        foreach (['payer_type' => 'patient', 'company_id' => $this->companyB->id, 'company_unit_id' => 1, 'pricing_snapshot' => ['fake'], 'context_encrypted' => 'fake'] as $field => $value) {
            $this->postJson($this->companyBase, [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        expect(DB::table('exam_orders')->count())->toBe(0);
    });

    it('fails closed on mismatched encrypted company evidence', function (): void {
        $this->post($this->companyBase, $this->input)->assertRedirect();
        $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
        $context = $this->context->run($this->a['tenant'], fn () => $order->contextSnapshot());
        $context['company_id'] = $this->companyB->id;
        DB::table('exam_orders')->where('id', $order->id)->update(['context_encrypted' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))]);
        $this->getJson($this->base.'/'.$order->public_id)->assertUnprocessable();
        $this->postJson($this->base.'/'.$order->public_id.'/authorize', ['expected_status' => 'draft'])->assertUnprocessable();
        expect(DB::table('exam_orders')->where('id', $order->id)->value('status'))->toBe('draft');
    });

    it('rolls back company payer creation when audit fails', function (): void {
        app()->instance(RegistryAudit::class, new class
        {
            public function record(mixed ...$arguments): never
            {
                throw new RuntimeException('Synthetic company order audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            expect(fn () => $this->post($this->companyBase, $this->input))->toThrow(RuntimeException::class);
            expect(DB::table('exam_orders')->count())->toBe(0)->and(DB::table('exam_order_items')->count())->toBe(0);
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
    });

    it('paginates company choices and escapes company names and encrypted payer identity', function (): void {
        $this->context->run($this->a['tenant'], function (): void {
            $this->companyA->update(['name' => '<script>payer()</script>']);
            foreach (range(1, 20) as $number) {
                Company::query()->create(['name' => 'Pagadora '.$number, 'is_active' => true]);
            }
        });
        $this->get($this->base.'/payers')->assertOk()->assertSee('page=2', false)->assertSee('&lt;script&gt;payer()&lt;/script&gt;', false)->assertDontSee('<script>payer()</script>', false);
        $this->post($this->companyBase, $this->input)->assertRedirect();
        $id = DB::table('exam_orders')->value('public_id');
        $this->get($this->base.'/'.$id)->assertOk()->assertSee('&lt;script&gt;payer()&lt;/script&gt;', false)->assertDontSee('<script>payer()</script>', false);
    });
});

describe('creation idempotency', function (): void {
    it('replays one order for equivalent input without repricing or recreating canceled orders', function (): void {
        $this->post($this->base, $this->input)->assertRedirect();
        $order = $this->context->run($this->a['tenant'], fn () => ExamOrder::query()->sole());
        $this->context->run($this->a['tenant'], fn () => $this->dataA['exam']->update(['price' => '77.77', 'is_active' => false]));
        $response = $this->post($this->base, ['exam_ids' => array_reverse($this->input['exam_ids']), 'idempotency_key' => strtoupper($this->input['idempotency_key'])]);
        $response->assertRedirect($this->base.'/'.$order->public_id);
        $this->post($this->base.'/'.$order->public_id.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->assertRedirect();
        $this->post($this->base, $this->input)->assertRedirect($this->base.'/'.$order->public_id);
        $this->context->run($this->a['tenant'], function () use ($order): void {
            expect(ExamOrder::query()->count())->toBe(1);
            expect($order->fresh()->status)->toBe('canceled');
            expect($order->items()->orderBy('id')->pluck('unit_price')->all())->toBe(['12.34', '0.00']);
            expect($order->toArray())->not->toHaveKeys(['creation_key_hash', 'creation_request_hash']);
            expect($order->getRawOriginal('creation_key_hash'))->not->toBe($this->input['idempotency_key']);
        });
        expect(DB::table('activity_log')->where('event', 'exam_order_created')->count())->toBe(1);
        expect(DB::table('activity_log')->where('event', 'exam_order_replayed')->count())->toBe(2);
    });

    it('rejects reuse for different exams patient or payer and accepts a new submission key', function (): void {
        $this->post($this->base, $this->input)->assertRedirect();
        $this->postJson($this->base, ['exam_ids' => [$this->dataA['other']->id]] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $patient = $this->context->run($this->a['tenant'], fn () => Patient::query()->create(['name' => 'Outra pessoa sintética']));
        $this->postJson('http://'.$this->a['host'].'/patients/'.$patient->public_id.'/orders', $this->input)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $company = $this->context->run($this->a['tenant'], function (): Company {
            $this->a['user']->givePermissionTo('companies.view');

            return Company::query()->create(['name' => 'Pagadora sintética', 'is_active' => true]);
        });
        $this->postJson($this->base.'/company/'.$company->public_id, $this->input)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->post($this->base, ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertRedirect();
        expect(DB::table('exam_orders')->count())->toBe(2);
    });

    it('requires a UUID and rejects server hashes while preserving the key after validation errors', function (): void {
        foreach (['', 'not-a-uuid', ['unexpected']] as $key) {
            $this->postJson($this->base, ['idempotency_key' => $key] + $this->input)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        }
        $this->postJson($this->base, ['exam_ids' => $this->input['exam_ids']])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        foreach (['creation_key_hash', 'creation_request_hash'] as $field) {
            $this->postJson($this->base, [$field => str_repeat('a', 64)] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->from($this->base.'/create')->post($this->base, ['exam_ids' => []] + $this->input)->assertSessionHasErrors('exam_ids')->assertSessionHas('_old_input.idempotency_key', $this->input['idempotency_key']);
        $this->get($this->base.'/create')->assertOk()->assertSee($this->input['idempotency_key']);
    });

    it('revalidates authorization before replay and leaves the key reusable after a rolled back creation', function (): void {
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
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
        $this->withExceptionHandling();
        $this->post($this->base, $this->input)->assertRedirect();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('orders.manage'));
        $this->postJson($this->base, $this->input)->assertForbidden();
        expect(DB::table('exam_orders')->count())->toBe(1);
        expect(DB::table('activity_log')->where('event', 'exam_order_created')->count())->toBe(1);
    });

    it('scopes the same client key by clinic and actor and enforces the unique index and hash pair in SQL', function (): void {
        $this->post($this->base, $this->input)->assertRedirect();
        $this->postJson('http://'.$this->a['host'].'/logout')->assertSuccessful();
        $this->postJson('http://'.$this->b['host'].'/login', ['email' => $this->b['user']->email, 'password' => $this->b['password']])->assertOk();
        $baseB = 'http://'.$this->b['host'].'/patients/'.$this->dataB['patient']->public_id.'/orders';
        $this->post($baseB, ['exam_ids' => [$this->dataB['exam']->id]] + $this->input)->assertRedirect();
        $this->context->run($this->a['tenant'], function (): void {
            $otherActor = User::query()->create(['name' => 'Outro operador sintético', 'email' => 'outro@example.test', 'password' => Str::random(40)]);
            $otherActor->givePermissionTo(['patients.view', 'orders.view', 'orders.manage', 'catalog.view']);
            $otherActor->forceFill(['two_factor_confirmed_at' => now()])->save();
            auth()->setUser($otherActor);
            app(CreateExamOrder::class)->execute($this->dataA['patient'], $this->input['exam_ids'], $this->input['idempotency_key']);
            expect(ExamOrder::query()->count())->toBe(2);
        });
        $row = (array) DB::table('exam_orders')->where('tenant_id', $this->a['tenant']->id)->first();
        unset($row['id']);
        $row['public_id'] = (string) Str::ulid();
        expect(fn () => DB::table('exam_orders')->insert($row))->toThrow(QueryException::class);
        $row['public_id'] = (string) Str::ulid();
        $row['creation_key_hash'] = null;
        expect(fn () => DB::table('exam_orders')->insert($row))->toThrow(QueryException::class);
    });
});
