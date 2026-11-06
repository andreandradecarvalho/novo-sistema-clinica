<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Orders\Enums\ExceptionFinancialMode;
use App\Domain\Orders\Services\ExceptionFinancialPolicy;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Setting;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveExceptionFinancialPolicy
{
    public function execute(string $mode, int $revision, string $key): array
    {
        Validator::make(['mode' => $mode, 'expected_revision' => $revision, 'idempotency_key' => $key], self::rules())->validate();

        return DB::transaction(function () use ($mode, $revision, $key): array {
            $tenantId = app(TenantContext::class)->id();
            // Tenant lock serializes first creation; subsequent writes also lock the current setting and fresh actor.
            Tenant::query()->whereKey($tenantId)->whereIn('status', ['trial', 'active', 'past_due'])->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageExceptionPolicy', Setting::class);
            $setting = Setting::query()->where('key', ExceptionFinancialPolicy::KEY)->lockForUpdate()->first();
            $reader = app(ExceptionFinancialPolicy::class);
            $snapshot = $reader->snapshot($setting);
            $keyHash = hash('sha256', 'exception-financial-policy:v1|'.$tenantId.'|'.$actor->id.'|'.strtolower($key));
            $requestHash = ExceptionFinancialPolicy::requestHash($revision, $mode);
            foreach ($snapshot['history'] as $event) {
                if (hash_equals($event['key_hash'], $keyHash)) {
                    if (! hash_equals($event['request_hash'], $requestHash)) {
                        $this->refuse();
                    }
                    app(RegistryAudit::class)->record('settings', 'exception_financial_policy_replayed', $setting, Setting::class);

                    return $snapshot;
                }
            }
            if ($snapshot['revision'] !== $revision) {
                $this->refuse();
            }
            $now = now('UTC')->startOfSecond();
            if ($snapshot['history'] !== [] && $now->lessThan(array_last($snapshot['history'])['occurred_at'])) {
                $this->refuse();
            }
            $history = [...$snapshot['history'], ['revision' => $revision + 1, 'mode' => $mode, 'actor_id' => (int) $actor->id,
                'occurred_at' => $now->toIso8601String(), 'key_hash' => $keyHash, 'request_hash' => $requestHash]];
            $value = ['schema_version' => 1, 'ciphertext' => Crypt::encryptString(json_encode(['tenant_id' => $tenantId, 'key' => ExceptionFinancialPolicy::KEY, 'history' => $history], JSON_THROW_ON_ERROR))];
            if ($setting === null) {
                $setting = new Setting;
                $setting->forceFill(['key' => ExceptionFinancialPolicy::KEY, 'value' => $value, 'updated_at' => $now])->save();
            } else {
                // Exact tenant/key/row update; model events intentionally prohibit arbitrary configuration changes.
                DB::table('settings')->where('tenant_id', $tenantId)->where('key', ExceptionFinancialPolicy::KEY)->where('id', $setting->id)
                    ->update(['value' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => $now]);
                $setting->refresh();
            }
            app(RegistryAudit::class)->record('settings', 'exception_financial_policy_changed', $setting, Setting::class);

            return $reader->snapshot($setting);
        }, 3);
    }

    public static function rules(): array
    {
        return ['mode' => ['required', Rule::enum(ExceptionFinancialMode::class)], 'expected_revision' => ['required', 'integer', 'min:0', 'max:2147483646'], 'idempotency_key' => ['required', 'uuid']];
    }

    private function refuse(): never
    {
        throw ValidationException::withMessages(['financial_policy' => 'A política mudou ou a chave foi reutilizada. Reabra a configuração.']);
    }
}
