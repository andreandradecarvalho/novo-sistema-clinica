<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Executions\Services\ExecutionStartEvidence;
use App\Domain\Orders\Services\OrderCompletionEvidence;
use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * @property array<string, mixed>|null $pricing_snapshot
 * @property string|null $creation_key_hash
 * @property string|null $creation_request_hash
 */
final class ExamOrder extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted', 'creation_key_hash', 'creation_request_hash'];

    protected function casts(): array
    {
        return ['pricing_snapshot' => 'array', 'context_encrypted' => 'encrypted:array', 'authorized_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return HasMany<ExamOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExamOrderItem::class);
    }

    /** @return HasMany<ClinicalReceivable, $this> */
    public function receivables(): HasMany
    {
        return $this->hasMany(ClinicalReceivable::class);
    }

    public function contextSnapshot(): array
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant order evidence is forbidden.');
        }
        try {
            $context = json_decode(Crypt::decryptString((string) $this->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($context) || ($context['schema_version'] ?? null) !== 1 || ($context['patient_id'] ?? null) !== (int) $this->patient_id
                || ! is_string($context['payer_name'] ?? null) || trim($context['payer_name']) === '') {
                throw new LogicException('Unsupported order context.');
            }
            if (($this->payer_type === 'patient' && ($this->company_id !== null || isset($context['company_id'])))
                || ($this->payer_type === 'company' && (($context['payer_type'] ?? null) !== 'company' || $this->company_id === null
                    || ($context['company_id'] ?? null) !== (int) $this->company_id || ! array_key_exists('payer_cnpj', $context)
                    || ($context['payer_cnpj'] !== null && ! is_string($context['payer_cnpj']))))
                || ! in_array($this->payer_type, ['patient', 'company'], true)) {
                throw new LogicException('Unsupported payer evidence.');
            }
            foreach (['authorization', 'cancellation'] as $key) {
                if (array_key_exists($key, $context)) {
                    $event = $context[$key];
                    if (! is_array($event) || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1 || ! is_string($event['occurred_at'] ?? null)
                        || ! in_array($event['from_status'] ?? null, ['draft', 'authorized'], true)
                        || ($key === 'cancellation' && (! is_string($event['reason'] ?? null) || trim($event['reason']) === '' || mb_strlen($event['reason']) > 1000))) {
                        throw new LogicException('Unsupported order transition evidence.');
                    }
                }
            }

            if (in_array($this->status, ['in_progress', 'completed'], true)) {
                $at = ExecutionStartEvidence::validate($context['start'] ?? null, orderId: (int) $this->id);
                if ($this->authorized_at === null || $at->lessThan($this->authorized_at) || ! isset($context['authorization']) || isset($context['cancellation'])) {
                    throw new LogicException('Invalid order start sequence.');
                }
            } elseif (isset($context['start'])) {
                throw new LogicException('Unexpected order start.');
            }

            if ($this->status === 'completed') {
                OrderCompletionEvidence::validate($this, $context['completion'] ?? null);
            } elseif (isset($context['completion'])) {
                throw new LogicException('Unexpected order completion.');
            }

            // Closed visits must remain coherent even when this order spans several days/items.
            // contextSnapshot never calls the parent order; attendanceSnapshot here would recurse.
            foreach (Visit::query()->where('exam_order_id', $this->id)->where('status', 'completed')->orderBy('id')->get() as $visit) {
                if ((int) $visit->patient_id !== (int) $this->patient_id) {
                    throw new LogicException('Closed visit belongs to another patient.');
                }
                $visit->contextSnapshot();
            }

            return $context;
        } catch (Throwable) {
            throw ValidationException::withMessages(['transition' => 'O contexto do pedido está indisponível. Solicite revisão autorizada.']);
        }
    }

    protected static function booted(): void
    {
        self::creating(function (ExamOrder $order): void {
            $order->public_id ??= (string) Str::ulid();
        });
        self::updating(fn () => throw new LogicException('Use the authorized order transition Action.'));
        self::deleting(fn () => throw new LogicException('Orders cannot be deleted.'));
    }
}
