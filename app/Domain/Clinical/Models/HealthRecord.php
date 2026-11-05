<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/** @property CarbonImmutable|null $filled_at */
final class HealthRecord extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted', 'creation_key_hash'];

    protected function casts(): array
    {
        return ['filled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return HasMany<HealthAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(HealthAnswer::class);
    }

    public static function requestHash(int $visitId, int $questionId, string $answer): string
    {
        // Store this fingerprint only inside the encrypted context, never as a searchable health-data hash.
        return hash('sha256', json_encode(['visit_id' => $visitId, 'question_id' => $questionId, 'answer' => $answer], JSON_THROW_ON_ERROR));
    }

    public function snapshot(): array
    {
        $tenant = app(TenantContext::class)->id();
        if ((int) $this->tenant_id !== $tenant || (int) $this->getRawOriginal('tenant_id') !== $tenant) {
            throw new LogicException('Cross-tenant clinical evidence is forbidden.');
        }
        try {
            $context = json_decode(Crypt::decryptString((string) $this->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            $visit = $this->visit;
            if (! is_array($context) || ($context['schema_version'] ?? null) !== 1 || ($context['tenant_id'] ?? null) !== $tenant
                || ($context['public_id'] ?? null) !== $this->public_id || ($context['patient_id'] ?? null) !== (int) $this->patient_id
                || ($context['visit_id'] ?? null) !== (int) $this->visit_id || ($context['appointment_id'] ?? null) !== (int) $this->appointment_id
                || ($context['source'] ?? null) !== 'visit' || ! is_int($context['actor_id'] ?? null) || $context['actor_id'] < 1 || $context['actor_id'] !== (int) $this->filled_by
                || ! is_string($context['occurred_at'] ?? null) || ! is_string($context['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $context['key_hash'])
                || $context['key_hash'] !== $this->creation_key_hash || ! is_string($context['request_hash'] ?? null)
                || $this->deleted_at !== null || $this->legacy_id !== null || $visit === null || (int) $visit->patient_id !== (int) $this->patient_id
                || (int) $visit->appointment_id !== (int) $this->appointment_id || $this->filled_at === null) {
                throw new LogicException('Invalid clinical record evidence.');
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $context['occurred_at']);
            if (! $at || $at->getOffset() !== 0 || $at->format('Y-m-d\TH:i:sP') !== $context['occurred_at'] || ! $at->equalTo($this->filled_at) || $at->lessThan($visit->checked_in_at)) {
                throw new LogicException('Invalid clinical collection time.');
            }
            $visit->attendanceSnapshot();
            $answer = $this->answers()->sole()->snapshot();
            if (! hash_equals(self::requestHash((int) $this->visit_id, $answer['question_id'], $answer['answer']), $context['request_hash'])) {
                throw new LogicException('Answer differs from collection evidence.');
            }

            return $context + ['answer' => $answer];
        } catch (Throwable) {
            throw ValidationException::withMessages(['health_record' => 'O registro clínico está indisponível. Solicite revisão autorizada.']);
        }
    }

    protected static function booted(): void
    {
        self::creating(function (HealthRecord $record): void {
            $record->public_id ??= (string) Str::ulid();
        });
        self::updating(fn () => throw new LogicException('Clinical records are immutable.'));
        self::deleting(fn () => throw new LogicException('Clinical records cannot be deleted.'));
    }
}
