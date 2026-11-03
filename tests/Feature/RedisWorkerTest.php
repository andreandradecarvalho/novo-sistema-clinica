<?php

declare(strict_types=1);

use App\Domain\Tenancy\Services\TenantCache;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\Support\Clinics;
use Tests\Support\ContextProbeJob;

it('processes A then a failure then B in the same persistent Redis worker without leaking ACL', function (): void {
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    config(['cache.default' => 'redis']);
    app(PermissionRegistrar::class)->initializeCache();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed();
    $a = Clinics::create();
    $b = Clinics::create();
    $context = app(TenantContext::class);
    $queue = 'testing-'.strtolower((string) Str::ulid());
    $key = 'probe:'.$queue;
    $cache = app(TenantCache::class);
    $context->run($a['tenant'], fn () => expect($cache->remember($key, 60, fn () => 'A'))->toBe('A'));
    $context->run($b['tenant'], fn () => expect($cache->remember($key, 60, fn () => 'B'))->toBe('B'));
    $context->run($a['tenant'], fn () => $a['user']->givePermissionTo('users.view'));
    foreach ([[$a, false], [$a, true], [$b, false]] as [$clinic, $fail]) {
        $context->run($clinic['tenant'], fn () => Queue::connection('redis')->push(new ContextProbeJob($key, $clinic['tenant']->id, $clinic['user']->id, $fail), '', $queue));
    }
    $process = new Process([PHP_BINARY, 'artisan', 'queue:work', 'redis', '--queue='.$queue, '--max-jobs=3', '--stop-when-empty', '--tries=1', '--sleep=0', '--timeout=120'], base_path(), [
        'APP_ENV' => 'testing', 'DB_DATABASE' => 'novo_sistema_clinica_testing', 'CACHE_STORE' => 'redis',
        'REDIS_PREFIX' => 'precordis-testing-', 'QUEUE_CONNECTION' => 'redis',
    ]);
    $process->setTimeout(45);
    try {
        $process->mustRun();
        $results = array_map(fn (string $value) => json_decode($value, true, flags: JSON_THROW_ON_ERROR), Redis::lrange($key, 0, -1));
        expect($results)->toHaveCount(3);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(1);
        expect(array_column($results, 'tenant_id'))->toBe([$a['tenant']->id, $a['tenant']->id, $b['tenant']->id]);
        expect(array_column($results, 'team_id'))->toBe(array_column($results, 'tenant_id'));
        expect(array_column($results, 'user_tenant'))->toBe(array_column($results, 'tenant_id'));
        expect(array_column($results, 'can_view'))->toBe([true, true, false]);
        expect($context->current())->toBeNull();
    } finally {
        Redis::del($key);
        foreach ([$a, $b] as $clinic) {
            $context->run($clinic['tenant'], fn () => $cache->forget($key));
        }
    }
});
