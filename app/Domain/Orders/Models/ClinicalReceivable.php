<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * @property string|null $transition_encrypted
 * @property string $amount
 * @property CarbonImmutable $due_date
 * @property CarbonImmutable|null $settled_at
 */
final class ClinicalReceivable extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['idempotency_key', 'transition_encrypted'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'immutable_date', 'settled_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ExamOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ExamOrder::class, 'exam_order_id');
    }

    public function transitionSnapshot(): ?array
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant financial evidence is forbidden.');
        }
        try {
            $raw = $this->getRawOriginal('transition_encrypted');
            if ($this->status === 'open' && $raw === null && $this->settled_at === null) {
                return null;
            }
            $event = json_decode(Crypt::decryptString((string) $raw), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($event) || ($event['schema_version'] ?? null) !== 1 || ($event['tenant_id'] ?? null) !== (int) $this->tenant_id
                || ($event['receivable_id'] ?? null) !== (int) $this->id || ($event['exam_order_id'] ?? null) !== (int) $this->exam_order_id
                || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1 || ! is_string($event['reason'] ?? null)
                || trim($event['reason']) === '' || mb_strlen($event['reason']) > 1000
                || ! in_array($event['operation'] ?? null, ['settle', 'cancel'], true)
                || ! is_string($event['occurred_at'] ?? null) || ! is_string($event['key_hash'] ?? null) || ! is_string($event['request_hash'] ?? null)
                || ! is_string($event['record_hash'] ?? null) || ! hash_equals($this->recordFingerprint(), $event['record_hash'])
                || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash']) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['request_hash'])) {
                throw new LogicException('Invalid financial evidence.');
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
            if (! $at || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || $at->getOffset() !== 0
                || ($event['operation'] === 'settle' && ($this->status !== 'paid' || $this->settled_at === null || ! $at->equalTo($this->settled_at)))
                || ($event['operation'] === 'cancel' && ($this->status !== 'canceled' || $this->settled_at !== null))) {
                throw new LogicException('Financial state differs from its evidence.');
            }

            return $event;
        } catch (Throwable) {
            throw ValidationException::withMessages(['receivable' => 'A evidência financeira está indisponível. Solicite revisão autorizada.']);
        }
    }

    public function recordFingerprint(): string
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant financial fingerprint is forbidden.');
        }

        return hash('sha256', json_encode(['tenant_id' => (int) $this->tenant_id, 'id' => (int) $this->id, 'exam_order_id' => (int) $this->exam_order_id,
            'amount' => $this->amount, 'due_date' => $this->due_date->format('Y-m-d'), 'payer_type' => $this->payer_type,
            'company_id' => $this->company_id, 'external_ref' => $this->external_ref], JSON_THROW_ON_ERROR));
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Receivable changes require an explicit financial workflow.'));
        self::deleting(fn () => throw new LogicException('Receivables cannot be deleted.'));
    }
}
