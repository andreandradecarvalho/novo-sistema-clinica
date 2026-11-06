<?php

declare(strict_types=1);

use App\Domain\Orders\Actions\SaveExceptionFinancialPolicy;
use App\Domain\Orders\Services\ExceptionFinancialPolicy;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Setting;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $clinic['user']->forceFill(['two_factor_confirmed_at' => now()])->save();
            $clinic['user']->givePermissionTo(['financial_policies.view', 'financial_policies.manage', 'receivables.view', 'receivables.manage']);
        });
    }
    $this->postJson('http://'.$this->a['host'].'/login', ['email' => $this->a['user']->email, 'password' => $this->a['password']])->assertOk();
    $this->withSession(['auth.password_confirmed_at' => time()]);
    $this->path = 'http://'.$this->a['host'].'/settings/exception-financial-policy';
    $this->input = ['mode' => 'review_required', 'expected_revision' => 0, 'idempotency_key' => (string) Str::uuid()];
});

afterEach(function (): void {
    $this->context->set(null);
    DB::rollBack();
});

it('starts unconfigured and stores independent explicit policies and version histories per clinic', function (): void {
    $this->get($this->path)->assertOk()->assertSee('Não configurada');
    $this->post($this->path, $this->input)->assertRedirect($this->path);
    $this->context->run($this->a['tenant'], function (): void {
        $snapshot = app(ExceptionFinancialPolicy::class)->current();
        expect($snapshot['revision'])->toBe(1)->and($snapshot['mode'])->toBe('review_required');
        expect($snapshot['history'][0]['actor_id'])->toBe((int) $this->a['user']->id);
        expect(Setting::query()->sole()->toArray())->not->toHaveKey('value');
    });
    $this->context->run($this->b['tenant'], function (): void {
        expect(app(ExceptionFinancialPolicy::class)->current())->toBe(['revision' => 0, 'mode' => null, 'history' => []]);
        auth()->setUser($this->b['user']->fresh());
        app(SaveExceptionFinancialPolicy::class)->execute('charge_completed', 0, (string) Str::uuid());
        expect(app(ExceptionFinancialPolicy::class)->current()['mode'])->toBe('charge_completed');
    });
    $this->get($this->path)->assertOk()->assertSee('Exigir revisão financeira');
    expect(DB::table('activity_log')->where('event', 'exception_financial_policy_changed')->whereNotNull('properties')->count())->toBe(0);
});

it('preserves old revisions and replays historical intent without reverting the current policy', function (): void {
    $this->post($this->path, $this->input)->assertRedirect();
    $second = ['mode' => 'charge_contracted', 'expected_revision' => 1, 'idempotency_key' => (string) Str::uuid()];
    $this->post($this->path, $second)->assertRedirect();
    $before = DB::table('settings')->where('tenant_id', $this->a['tenant']->id)->sole();
    $this->post($this->path, $this->input)->assertRedirect();
    $this->postJson($this->path, ['mode' => 'charge_completed'] + $this->input)->assertUnprocessable();
    $this->postJson($this->path, ['idempotency_key' => (string) Str::uuid()] + $this->input)->assertUnprocessable();
    expect(DB::table('settings')->where('tenant_id', $this->a['tenant']->id)->sole())->toEqual($before);
    $this->context->run($this->a['tenant'], function (): void {
        $snapshot = app(ExceptionFinancialPolicy::class)->current();
        expect($snapshot['mode'])->toBe('charge_contracted')->and($snapshot['revision'])->toBe(2);
        expect(array_column($snapshot['history'], 'mode'))->toBe(['review_required', 'charge_contracted']);
    });
    expect(DB::table('activity_log')->where('event', 'exception_financial_policy_changed')->count())->toBe(2);
    expect(DB::table('activity_log')->where('event', 'exception_financial_policy_replayed')->count())->toBe(1);
});

it('requires dedicated reading and management permissions and recent password on writes', function (): void {
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('financial_policies.manage'));
    $this->get($this->path)->assertOk()->assertDontSee('Registrar política');
    $this->postJson($this->path, $this->input)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->revokePermissionTo('financial_policies.view'));
    $this->getJson($this->path)->assertForbidden();
    $this->context->run($this->a['tenant'], fn () => $this->a['user']->givePermissionTo(['financial_policies.view', 'financial_policies.manage']));
    $this->withSession(['auth.password_confirmed_at' => 0])->post($this->path, $this->input)->assertRedirect();
    expect(DB::table('settings')->count())->toBe(0);
});

it('rejects injected ownership content invalid policies revisions and non UUID keys', function (): void {
    foreach (['tenant_id' => $this->b['tenant']->id, 'key' => 'unrelated', 'value' => [], 'revision' => 3, 'history' => [], 'actor_id' => 1, 'mode' => 'automatic_refund', 'expected_revision' => -1, 'idempotency_key' => 'invalid'] as $field => $value) {
        // Non-empty forbidden content is required for Laravel prohibited fields.
        $this->postJson($this->path, [$field => is_array($value) ? ['invalid'] : $value] + $this->input)->assertUnprocessable();
    }
    expect(DB::table('settings')->count())->toBe(0);
});

it('fails closed for copied corrupted or non chronological policy history without resetting configuration', function (): void {
    $this->post($this->path, $this->input)->assertRedirect();
    $row = DB::table('settings')->where('tenant_id', $this->a['tenant']->id)->sole();
    $wrapper = json_decode($row->value, true, 512, JSON_THROW_ON_ERROR);
    foreach (['tenant_id' => (int) $this->b['tenant']->id, 'key' => 'unrelated', 'history' => []] as $field => $value) {
        $context = json_decode(Crypt::decryptString($wrapper['ciphertext']), true, 512, JSON_THROW_ON_ERROR);
        $context[$field] = $value;
        $changed = ['schema_version' => 1, 'ciphertext' => Crypt::encryptString(json_encode($context, JSON_THROW_ON_ERROR))];
        DB::table('settings')->where('id', $row->id)->update(['value' => json_encode($changed, JSON_THROW_ON_ERROR)]);
        $this->getJson($this->path)->assertUnprocessable();
        $this->postJson($this->path, $this->input)->assertUnprocessable();
    }
    DB::table('settings')->where('id', $row->id)->update(['value' => '{"schema_version":1,"ciphertext":"corrupt"}']);
    $this->getJson($this->path)->assertUnprocessable();
    DB::table('settings')->where('id', $row->id)->update(['value' => $row->value, 'updated_at' => '2020-01-01 00:00:00']);
    $this->getJson($this->path)->assertUnprocessable();
});

it('requires tenant context and fresh active actors with second factor in direct actions', function (): void {
    expect(fn () => app(ExceptionFinancialPolicy::class)->current())->toThrow(MissingTenant::class);
    $this->context->run($this->a['tenant'], function (): void {
        foreach ([['is_active' => false], ['is_active' => true, 'two_factor_confirmed_at' => null]] as $change) {
            $this->a['user']->forceFill($change)->save();
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(SaveExceptionFinancialPolicy::class)->execute('review_required', 0, (string) Str::uuid()))->toThrow(AuthorizationException::class);
        }
    });
    expect(DB::table('settings')->count())->toBe(0);
});

it('rolls back configuration when transactional audit fails and forbids arbitrary model updates', function (): void {
    app()->instance(RegistryAudit::class, new class
    {
        public function record(...$arguments): never
        {
            throw new RuntimeException('Synthetic policy audit failure.');
        }
    });
    try {
        $this->context->run($this->a['tenant'], function (): void {
            auth()->setUser($this->a['user']->fresh());
            expect(fn () => app(SaveExceptionFinancialPolicy::class)->execute('review_required', 0, (string) Str::uuid()))->toThrow(RuntimeException::class);
        });
    } finally {
        app()->forgetInstance(RegistryAudit::class);
    }
    expect(DB::table('settings')->count())->toBe(0);
    $this->post($this->path, $this->input)->assertRedirect();
    $this->context->run($this->a['tenant'], function (): void {
        $setting = Setting::query()->sole();
        expect(fn () => $setting->forceFill(['key' => 'changed'])->save())->toThrow(LogicException::class);
        expect(fn () => $setting->delete())->toThrow(LogicException::class);
    });
});

it('enforces settings key uniqueness within each clinic and tenant foreign keys', function (): void {
    $this->post($this->path, $this->input)->assertRedirect();
    expect(fn () => DB::table('settings')->insert(['tenant_id' => $this->a['tenant']->id, 'key' => ExceptionFinancialPolicy::KEY]))->toThrow(QueryException::class);
    $missingTenant = (int) DB::table('tenants')->max('id') + 1;
    expect(fn () => DB::table('settings')->insert(['tenant_id' => $missingTenant, 'key' => ExceptionFinancialPolicy::KEY]))->toThrow(QueryException::class);
});

it('allows trial clinics and rejects read only or suspended tenants in direct configuration writes', function (): void {
    $this->context->run($this->a['tenant'], function (): void {
        auth()->setUser($this->a['user']->fresh());
        foreach (['trial', 'past_due'] as $index => $status) {
            $this->a['tenant']->forceFill(['status' => $status])->save();
            $snapshot = app(SaveExceptionFinancialPolicy::class)->execute('review_required', $index, (string) Str::uuid());
            expect($snapshot['revision'])->toBe($index + 1);
        }
        foreach (['read_only', 'suspended'] as $status) {
            $this->a['tenant']->forceFill(['status' => $status])->save();
            expect(fn () => app(SaveExceptionFinancialPolicy::class)->execute('charge_completed', 2, (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
        }
        expect(app(ExceptionFinancialPolicy::class)->current()['revision'])->toBe(2);
    });
});
