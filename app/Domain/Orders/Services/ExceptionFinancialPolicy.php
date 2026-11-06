<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Enums\ExceptionFinancialMode;
use App\Domain\Tenancy\Models\Setting;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class ExceptionFinancialPolicy
{
    public const string KEY = 'clinical.exception_financial_policy';

    public function current(): array
    {
        return $this->snapshot(Setting::query()->where('key', self::KEY)->first());
    }

    public function snapshot(?Setting $setting): array
    {
        $tenantId = app(TenantContext::class)->id();
        if ($setting === null) {
            return ['revision' => 0, 'mode' => null, 'history' => []];
        }
        if ((int) $setting->tenant_id !== $tenantId || (int) $setting->getRawOriginal('tenant_id') !== $tenantId) {
            throw new LogicException('Cross-tenant financial policy evidence is forbidden.');
        }
        try {
            $value = $setting->value;
            if ($setting->key !== self::KEY || ! is_array($value) || ($value['schema_version'] ?? null) !== 1 || ! is_string($value['ciphertext'] ?? null)) {
                throw new LogicException('Invalid financial policy envelope.');
            }
            $context = json_decode(Crypt::decryptString($value['ciphertext']), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($context) || ($context['tenant_id'] ?? null) !== $tenantId || ($context['key'] ?? null) !== self::KEY
                || ! is_array($context['history'] ?? null) || ! array_is_list($context['history']) || $context['history'] === []) {
                throw new LogicException('Invalid financial policy owner/history.');
            }
            $previous = null;
            $keys = [];
            foreach ($context['history'] as $index => $event) {
                if (! is_array($event) || ($event['revision'] ?? null) !== $index + 1
                    || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1 || ! is_string($event['occurred_at'] ?? null)
                    || ! is_string($event['mode'] ?? null) || ExceptionFinancialMode::tryFrom($event['mode']) === null
                    || ! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash']) || in_array($event['key_hash'], $keys, true)
                    || ($event['request_hash'] ?? null) !== self::requestHash($index, $event['mode'])) {
                    throw new LogicException('Invalid financial policy revision.');
                }
                $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
                if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || ($previous !== null && $at->lessThan($previous))) {
                    throw new LogicException('Invalid financial policy chronology.');
                }
                $previous = $at;
                $keys[] = $event['key_hash'];
            }
            if ($setting->updated_at === null || ! $previous->equalTo($setting->updated_at)) {
                throw new LogicException('Financial policy time differs.');
            }

            return ['revision' => count($context['history']), 'mode' => $event['mode'], 'history' => $context['history']];
        } catch (Throwable) {
            throw ValidationException::withMessages(['financial_policy' => 'A política financeira está indisponível. Solicite revisão autorizada.']);
        }
    }

    public static function requestHash(int $revision, string $mode): string
    {
        return hash('sha256', json_encode(['expected_revision' => $revision, 'mode' => $mode], JSON_THROW_ON_ERROR));
    }
}
