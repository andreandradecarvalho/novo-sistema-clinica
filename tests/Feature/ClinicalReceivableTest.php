<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Companies\Models\Company;
use App\Domain\Orders\Actions\CreateClinicalReceivable;
use App\Domain\Orders\Actions\CreateExamOrder;
use App\Domain\Orders\Actions\TransitionClinicalReceivable;
use App\Domain\Orders\Actions\TransitionExamOrder;
use App\Domain\Orders\Enums\OrderTransition;
use App\Domain\Orders\Enums\ReceivableTransition;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    foreach (['a', 'b'] as $key) {
        $clinic = $this->$key;
        $this->{'data'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic): array {
            $actor = $clinic['user'];
            $actor->givePermissionTo(['patients.view', 'orders.view', 'orders.manage', 'catalog.view', 'companies.view', 'receivables.view', 'receivables.manage']);
            $actor->forceFill(['two_factor_confirmed_at' => now()])->save();
            auth()->setUser($actor->fresh());
            $patient = Patient::query()->create(['name' => 'Paciente sintético']);
            $exam = ExamType::query()->create(['code' => 'SYN-FIN', 'name' => 'Exame sintético', 'price' => '12.34']);
            $order = app(CreateExamOrder::class)->execute($patient, [$exam->id], (string) Str::uuid());
            app(TransitionExamOrder::class)->execute($patient, $order, OrderTransition::Authorize, 'draft');

            return compact('patient', 'exam', 'order');
        });
    }
    $this->base = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$this->dataA['order']->public_id;
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->input = ['due_date' => '2026-12-10'];
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('registers and reads an integral historical receivable and safely replays the same order', function (): void {
    $this->get($this->base)->assertOk()->assertSee('Contas a receber');
    $this->get($this->base.'/receivables')->assertOk()->assertSee('Registrar recebível integral')->assertHeader('Cache-Control', 'no-store, private');
    $this->context->run($this->a['tenant'], fn () => $this->dataA['exam']->update(['price' => '99.99', 'is_active' => false]));
    $this->post($this->base.'/receivables', $this->input)->assertRedirect($this->base.'/receivables');
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    $this->postJson($this->base.'/receivables', ['due_date' => '2026-12-11'])->assertUnprocessable()->assertJsonValidationErrors('receivable');
    $this->get($this->base.'/receivables')->assertOk()->assertSee('12.34')->assertSee('10/12/2026')->assertSee('Em aberto')->assertDontSee('99.99')->assertDontSee('Registrar recebível integral');
    $this->context->run($this->a['tenant'], function (): void {
        $receivable = ClinicalReceivable::query()->sole();
        expect($receivable->amount)->toBe('12.34')->and($receivable->payer_type)->toBe('patient')->and($receivable->company_id)->toBeNull();
        expect($receivable->external_ref)->toBeNull()->and($receivable->settled_at)->toBeNull()->and($receivable->toArray())->not->toHaveKey('idempotency_key');
        expect(fn () => $receivable->update(['status' => 'paid']))->not->toThrow(Throwable::class); // guarded input cannot mutate state
        expect($receivable->fresh()->status)->toBe('open');
        expect(fn () => $receivable->forceFill(['status' => 'paid'])->save())->toThrow(LogicException::class);
        expect(fn () => $receivable->delete())->toThrow(LogicException::class);
    });
    expect(DB::table('activity_log')->where('event', 'clinical_receivable_created')->count())->toBe(1);
    expect(DB::table('activity_log')->where('event', 'clinical_receivable_replayed')->count())->toBe(1);
    expect(DB::table('activity_log')->where('log_name', 'receivables')->pluck('properties')->filter())->toBeEmpty();
    expect(DB::table('activity_log')->where('event', 'clinical_receivable_created')->value('causer_id'))->toBe($this->a['user']->id);
});

it('blocks order cancellation after receivable creation without changing items or authorization', function (): void {
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    $before = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first();
    $this->postJson($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Revisão sintética'])->assertUnprocessable()->assertJsonValidationErrors('transition');
    $after = DB::table('exam_orders')->where('id', $this->dataA['order']->id)->first();
    expect($after)->toEqual($before);
    expect(DB::table('exam_order_items')->where('exam_order_id', $this->dataA['order']->id)->pluck('status')->all())->toBe(['authorized']);
});

it('rejects malformed dates and client financial fields without writes', function (): void {
    foreach (['', '2026-02-30', '10/12/2026', '0999-12-31', '10000-01-01'] as $date) {
        $this->postJson($this->base.'/receivables', ['due_date' => $date])->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }
    foreach (['id' => 77, 'tenant_id' => $this->b['tenant']->id, 'exam_order_id' => $this->dataB['order']->id, 'company_id' => 1, 'payer_type' => 'company', 'amount' => '0.01', 'status' => 'paid', 'settled_at' => '2026-12-10', 'external_ref' => 'fake', 'idempotency_key' => 'fake', 'created_at' => '2026-12-10', 'updated_at' => '2026-12-10'] as $field => $value) {
        $this->postJson($this->base.'/receivables', [$field => $value] + $this->input)->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    expect(DB::table('clinical_receivables')->count())->toBe(0);
});

it('requires separate current permissions and confirmed two factor including on replay', function (): void {
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    foreach (['receivables.manage', 'receivables.view', 'orders.view', 'patients.view'] as $permission) {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo($permission));
        $this->postJson($this->base.'/receivables', $this->input)->assertForbidden();
        if ($permission !== 'receivables.manage') {
            $this->getJson($this->base.'/receivables')->assertForbidden();
        } else {
            $this->get($this->base.'/receivables')->assertOk()->assertDontSee('Registrar recebível integral');
        }
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo($permission));
    }
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => false])->save());
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], $this->input['due_date']))->toThrow(AuthorizationException::class);
    });
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->forceFill(['is_active' => true, 'two_factor_confirmed_at' => null])->save());
    $this->postJson($this->base.'/receivables', $this->input)->assertForbidden();
    expect(DB::table('clinical_receivables')->count())->toBe(1);
});

it('isolates foreign orders patients actions and missing tenant context', function (): void {
    $foreign = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$this->dataB['order']->public_id.'/receivables';
    $this->get($foreign)->assertNotFound();
    $this->postJson($foreign, $this->input)->assertNotFound();
    $another = $this->context->run($this->a['tenant'], fn () => Patient::query()->create(['name' => 'Outro paciente sintético']));
    $wrong = 'http://'.$this->a['host'].'/patients/'.$another->public_id.'/orders/'.$this->dataA['order']->public_id.'/receivables';
    $this->postJson($wrong, $this->input)->assertNotFound();
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        expect(fn () => app(CreateClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataB['order'], $this->input['due_date']))->toThrow(ModelNotFoundException::class);
    });
    $this->context->set(null);
    expect(fn () => ClinicalReceivable::query()->count())->toThrow(MissingTenant::class);
    expect(DB::table('clinical_receivables')->count())->toBe(0);
});

it('refuses incompatible order state evidence item state or totals and permits an exact zero amount', function (): void {
    $orderId = $this->dataA['order']->id;
    $original = (array) DB::table('exam_orders')->where('id', $orderId)->first();
    foreach ([['status' => 'draft'], ['status' => 'canceled'], ['authorized_at' => null], ['context_encrypted' => 'invalid'], ['pricing_snapshot' => '{}']] as $change) {
        DB::table('exam_orders')->where('id', $orderId)->update($change);
        $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable();
        DB::table('exam_orders')->where('id', $orderId)->update($original);
    }
    foreach ([['status' => 'draft'], ['quantity' => 2], ['discount_amount' => '1.00']] as $change) {
        DB::table('exam_order_items')->where('exam_order_id', $orderId)->update($change);
        $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable();
        DB::table('exam_order_items')->where('exam_order_id', $orderId)->update(['status' => 'authorized', 'quantity' => 1, 'discount_amount' => '0.00']);
    }
    // Two historical items whose sum exceeds DECIMAL(12,2), without overflowing PHP or rounding.
    $item = (array) DB::table('exam_order_items')->where('exam_order_id', $orderId)->first();
    DB::table('exam_order_items')->where('id', $item['id'])->update(['unit_price' => '9999999999.99']);
    unset($item['id']);
    DB::table('exam_order_items')->insert($item);
    $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable();
    DB::table('exam_order_items')->where('exam_order_id', $orderId)->update(['unit_price' => '0.00']);
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    expect(DB::table('clinical_receivables')->value('amount'))->toBe('0.00');
});

it('preserves company payer history and refuses corporate reading without company visibility', function (): void {
    $order = $this->context->run($this->a['tenant'], function (): ExamOrder {
        auth()->setUser($this->a['user']->fresh());
        $company = Company::query()->create(['name' => '<script>Empresa sintética</script>', 'cnpj' => null]);
        $order = app(CreateExamOrder::class)->execute($this->dataA['patient'], [$this->dataA['exam']->id], (string) Str::uuid(), $company);
        app(TransitionExamOrder::class)->execute($this->dataA['patient'], $order, OrderTransition::Authorize, 'draft');
        $company->update(['name' => 'Outro nome sintético', 'is_active' => false]);

        return $order;
    });
    $url = 'http://'.$this->a['host'].'/patients/'.$this->dataA['patient']->public_id.'/orders/'.$order->public_id.'/receivables';
    $this->post($url, $this->input)->assertRedirect();
    $this->get($url)->assertOk()->assertSee('&lt;script&gt;Empresa sintética&lt;/script&gt;', false)->assertDontSee('Outro nome sintético');
    expect(DB::table('clinical_receivables')->value('payer_type'))->toBe('company');
    expect(DB::table('clinical_receivables')->value('company_id'))->toBe($order->company_id);
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('companies.view'));
    $this->getJson($url)->assertForbidden();
    $this->postJson($url, $this->input)->assertForbidden();
});

it('rolls back receivable and idempotency when transactional audit fails', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('Synthetic audit failure');
        }
    });
    $this->withoutExceptionHandling();
    try {
        expect(fn () => $this->post($this->base.'/receivables', $this->input))->toThrow(RuntimeException::class);
        expect(DB::table('clinical_receivables')->count())->toBe(0);
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    expect(DB::table('clinical_receivables')->count())->toBe(1);
});

it('enforces tenant composite foreign keys unique idempotency and nonnegative database amount', function (): void {
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    $row = (array) DB::table('clinical_receivables')->sole();
    unset($row['id']);
    foreach ([[], ['exam_order_id' => $this->dataB['order']->id, 'idempotency_key' => 'foreign-order'], ['company_id' => $this->context->run($this->b['tenant'], fn () => Company::query()->create(['name' => 'Empresa B']))->id, 'idempotency_key' => 'foreign-company'], ['amount' => '-0.01', 'idempotency_key' => 'negative']] as $change) {
        expect(fn () => DB::table('clinical_receivables')->insert(array_replace($row, $change)))->toThrow(QueryException::class);
    }
    expect(DB::table('clinical_receivables')->count())->toBe(1);
});

it('refuses replay of incompatible financial records without repairing or duplicating them', function (): void {
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    $row = (array) DB::table('clinical_receivables')->sole();
    foreach ([['amount' => '0.01'], ['status' => 'paid'], ['settled_at' => '2026-12-10 12:00:00'], ['external_ref' => 'synthetic-reference'], ['idempotency_key' => 'unsupported-key'], ['payer_type' => 'company']] as $change) {
        DB::table('clinical_receivables')->where('id', $row['id'])->update($change);
        $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable()->assertJsonValidationErrors('receivable');
        expect(DB::table('clinical_receivables')->count())->toBe(1);
        foreach ($change as $field => $value) {
            expect(DB::table('clinical_receivables')->value($field))->toBe($value);
        }
        DB::table('clinical_receivables')->where('id', $row['id'])->update($row);
    }
});

it('sums fractional historical cents exactly at the DECIMAL boundary', function (): void {
    $orderId = $this->dataA['order']->id;
    $item = (array) DB::table('exam_order_items')->where('exam_order_id', $orderId)->first();
    DB::table('exam_order_items')->where('id', $item['id'])->update(['unit_price' => '9999999999.98']);
    unset($item['id']);
    $item['unit_price'] = '0.01';
    DB::table('exam_order_items')->insert($item);
    $this->post($this->base.'/receivables', $this->input)->assertRedirect();
    expect(DB::table('clinical_receivables')->value('amount'))->toBe('9999999999.99');
});

describe('financial transitions', function (): void {
    beforeEach(function (): void {
        foreach (['a', 'b'] as $key) {
            $clinic = $this->$key;
            $data = $this->{'data'.strtoupper($key)};
            $this->{'receipt'.strtoupper($key)} = $this->context->run($clinic['tenant'], function () use ($clinic, $data): ClinicalReceivable {
                $clinic['user']->givePermissionTo(['receivables.settle', 'receivables.cancel']);
                auth()->setUser($clinic['user']->fresh());

                return app(CreateClinicalReceivable::class)->execute($data['patient'], $data['order'], '2026-12-10');
            });
        }
        $this->transitionBase = $this->base.'/receivables/'.$this->receiptA->id;
        $this->transitionInput = ['expected_status' => 'open', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Motivo sintético <script>unsafe()</script>'];
    });

    it('records a terminal financial transition once with encrypted evidence and preserves historical amounts', function (string $operation, string $status): void {
        $before = (array) DB::table('clinical_receivables')->where('id', $this->receiptA->id)->first();
        $this->post($this->transitionBase.'/'.$operation, $this->transitionInput)->assertRedirect();
        $row = DB::table('clinical_receivables')->where('id', $this->receiptA->id)->first();
        expect($row->status)->toBe($status)->and($row->transition_encrypted)->not->toContain($this->transitionInput['reason']);
        foreach (['amount', 'due_date', 'company_id', 'payer_type', 'idempotency_key', 'external_ref', 'exam_order_id', 'tenant_id', 'created_at'] as $field) {
            expect($row->$field)->toBe($before[$field]);
        }
        $this->context->run($this->a['tenant'], function () use ($operation): void {
            $receipt = $this->receiptA->fresh();
            $event = $receipt->transitionSnapshot();
            expect($event['operation'])->toBe($operation)->and($event['actor_id'])->toBe((int) $this->a['user']->id);
            expect($event['reason'])->toBe($this->transitionInput['reason']);
            expect($receipt->toArray())->not->toHaveKey('transition_encrypted');
            expect($receipt->settled_at === null)->toBe($operation !== 'settle');
        });
        $this->post($this->transitionBase.'/'.$operation, ['idempotency_key' => strtoupper($this->transitionInput['idempotency_key']), 'reason' => ' '.$this->transitionInput['reason'].' '] + $this->transitionInput)->assertRedirect();
        $this->postJson($this->transitionBase.'/'.$operation, ['reason' => 'Outro motivo'] + $this->transitionInput)->assertUnprocessable();
        $this->postJson($this->transitionBase.'/'.$operation, ['idempotency_key' => (string) Str::uuid()] + $this->transitionInput)->assertUnprocessable();
        $opposite = $operation === 'settle' ? 'cancel' : 'settle';
        $this->postJson($this->transitionBase.'/'.$opposite, $this->transitionInput)->assertUnprocessable();
        $this->get($this->base.'/receivables')->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        expect(DB::table('activity_log')->where('subject_id', $this->receiptA->id)->where('event', 'clinical_receivable_'.$status)->count())->toBe(1);
        expect(DB::table('activity_log')->where('subject_id', $this->receiptA->id)->where('event', 'clinical_receivable_transition_replayed')->count())->toBe(1);
        expect(DB::table('activity_log')->where('log_name', 'receivables')->pluck('properties')->filter())->toBeEmpty();
        $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable();
    })->with([['settle', 'paid'], ['cancel', 'canceled']]);

    it('permits order cancellation only after an evidenced financial cancellation and preserves replay', function (): void {
        $this->postJson($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
        $this->post($this->transitionBase.'/cancel', $this->transitionInput)->assertRedirect();
        $this->post($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertRedirect();
        expect(DB::table('exam_orders')->where('id', $this->dataA['order']->id)->value('status'))->toBe('canceled');
        $this->post($this->transitionBase.'/cancel', $this->transitionInput)->assertRedirect();
        $this->get($this->base.'/receivables')->assertOk()->assertSee('Cancelado');
        $this->postJson($this->base.'/receivables', $this->input)->assertUnprocessable();
    });

    it('keeps a paid receivable blocking order cancellation and refuses cancellation or reversal of the settlement', function (): void {
        $this->post($this->transitionBase.'/settle', $this->transitionInput)->assertRedirect();
        $this->postJson($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
        $this->postJson($this->transitionBase.'/cancel', $this->transitionInput)->assertUnprocessable();
        expect(DB::table('clinical_receivables')->where('id', $this->receiptA->id)->value('status'))->toBe('paid');
        expect(DB::table('exam_orders')->where('id', $this->dataA['order']->id)->value('status'))->toBe('authorized');
    });

    it('validates UUID reason expected state and forbids authoritative fields without flashing reason', function (): void {
        foreach ([['reason' => ''], ['reason' => str_repeat('x', 1001)], ['idempotency_key' => 'invalid'], ['expected_status' => 'paid']] as $change) {
            $this->postJson($this->transitionBase.'/settle', $change + $this->transitionInput)->assertUnprocessable();
        }
        foreach (['tenant_id' => $this->b['tenant']->id, 'exam_order_id' => $this->dataB['order']->id, 'amount' => '0.01', 'company_id' => 1, 'payer_type' => 'company', 'due_date' => '2026-12-11', 'status' => 'paid', 'settled_at' => '2026-12-10', 'external_ref' => 'fake', 'transition_encrypted' => 'fake'] as $field => $value) {
            $this->postJson($this->transitionBase.'/settle', [$field => $value] + $this->transitionInput)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->from($this->base.'/receivables')->post($this->transitionBase.'/settle', ['idempotency_key' => 'invalid'] + $this->transitionInput)->assertRedirect()->assertSessionMissing('_old_input.reason');
        expect(DB::table('clinical_receivables')->where('id', $this->receiptA->id)->value('status'))->toBe('open');
    });

    it('requires distinct transition permissions current two factor and active actor even for replays', function (): void {
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo(['receivables.settle', 'receivables.cancel']));
        foreach (['settle', 'cancel'] as $operation) {
            $this->postJson($this->transitionBase.'/'.$operation, $this->transitionInput)->assertForbidden();
        }
        $this->context->run($this->a['tenant'], function (): void {
            $this->a['user']->givePermissionTo('receivables.settle');
            $this->a['user']->revokePermissionTo('receivables.manage');
        });
        $this->post($this->transitionBase.'/settle', $this->transitionInput)->assertRedirect();
        $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('receivables.settle'));
        $this->postJson($this->transitionBase.'/settle', $this->transitionInput)->assertForbidden();
        $this->context->run($this->a['tenant'], function (): void {
            $this->a['user']->givePermissionTo('receivables.settle');
            $this->a['user']->forceFill(['two_factor_confirmed_at' => null])->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(TransitionClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], $this->receiptA, ReceivableTransition::Settle, $this->transitionInput['reason'], $this->transitionInput['idempotency_key'], 'open'))->toThrow(AuthorizationException::class);
        });
        $this->postJson($this->transitionBase.'/settle', $this->transitionInput)->assertForbidden();
    });

    it('refuses foreign receipts wrong parent relations direct action models and absent context', function (): void {
        foreach (['settle', 'cancel'] as $operation) {
            $this->postJson($this->base.'/receivables/'.$this->receiptB->id.'/'.$operation, $this->transitionInput)->assertNotFound();
        }
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(TransitionClinicalReceivable::class)->execute($this->dataA['patient'], $this->dataA['order'], $this->receiptB, ReceivableTransition::Cancel, 'Sintético', (string) Str::uuid(), 'open'))->toThrow(ModelNotFoundException::class);
            expect(fn () => $this->receiptB->transitionSnapshot())->toThrow(LogicException::class);
        });
        $this->context->set(null);
        expect(fn () => $this->receiptA->transitionSnapshot())->toThrow(MissingTenant::class);
    });

    it('fails closed on corrupted swapped or financially inconsistent terminal evidence', function (): void {
        $this->post($this->transitionBase.'/settle', $this->transitionInput)->assertRedirect();
        $row = (array) DB::table('clinical_receivables')->where('id', $this->receiptA->id)->first();
        foreach ([['transition_encrypted' => null], ['transition_encrypted' => 'corrupt'], ['amount' => '0.01'], ['due_date' => '2026-12-11'], ['settled_at' => null], ['status' => 'canceled'], ['external_ref' => 'unsupported']] as $change) {
            DB::table('clinical_receivables')->where('id', $row['id'])->update($change);
            $this->postJson($this->transitionBase.'/settle', $this->transitionInput)->assertUnprocessable();
            $this->getJson($this->base.'/receivables')->assertUnprocessable();
            $this->postJson($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
            DB::table('clinical_receivables')->where('id', $row['id'])->update($row);
        }
        DB::table('clinical_receivables')->where('id', $this->receiptB->id)->update(['transition_encrypted' => $row['transition_encrypted'], 'status' => 'paid', 'settled_at' => $row['settled_at']]);
        $this->context->run($this->b['tenant'], function (): void {
            expect(fn () => $this->receiptB->fresh()->transitionSnapshot())->toThrow(ValidationException::class);
        });
    });

    it('refuses forged unencrypted canceled status as authorization to cancel an order', function (): void {
        DB::table('clinical_receivables')->where('id', $this->receiptA->id)->update(['status' => 'canceled']);
        $this->postJson($this->base.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->assertUnprocessable();
        expect(DB::table('exam_orders')->where('id', $this->dataA['order']->id)->value('status'))->toBe('authorized');
    });

    it('rolls back financial transition evidence and date if audit persistence fails', function (): void {
        $before = DB::table('clinical_receivables')->where('id', $this->receiptA->id)->first();
        app()->instance(RegistryAudit::class, new class
        {
            public function record(mixed ...$arguments): never
            {
                throw new RuntimeException('Synthetic financial audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            expect(fn () => $this->post($this->transitionBase.'/settle', $this->transitionInput))->toThrow(RuntimeException::class);
            expect(DB::table('clinical_receivables')->where('id', $this->receiptA->id)->first())->toEqual($before);
        } finally {
            app()->forgetInstance(RegistryAudit::class);
        }
        $this->post($this->transitionBase.'/settle', $this->transitionInput)->assertRedirect();
    });

    it('refuses a historical amount discrepancy before settlement without repricing', function (): void {
        DB::table('clinical_receivables')->where('id', $this->receiptA->id)->update(['amount' => '0.01']);
        $this->postJson($this->transitionBase.'/settle', $this->transitionInput)->assertUnprocessable();
        expect(DB::table('clinical_receivables')->where('id', $this->receiptA->id)->value('status'))->toBe('open');
    });
});
