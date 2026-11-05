<?php

declare(strict_types=1);

namespace App\Domain\Visits\Models;

use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Services\ExecutionStartEvidence;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/** @property CarbonImmutable $checked_in_at */
final class Visit extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<ExamOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ExamOrder::class, 'exam_order_id');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return HasMany<ExamExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(ExamExecution::class);
    }

    /** @return HasMany<HealthRecord, $this> */
    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function contextSnapshot(): array
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant visit evidence is forbidden.');
        }
        try {
            $context = json_decode(Crypt::decryptString((string) $this->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($context) || ($context['schema_version'] ?? null) !== 1 || ($context['tenant_id'] ?? null) !== (int) $this->tenant_id
                || ($context['public_id'] ?? null) !== $this->public_id || ($context['patient_id'] ?? null) !== (int) $this->patient_id
                || $this->appointment_id === null || $this->exam_order_id === null || $this->unit_id === null
                || ($context['appointment_id'] ?? null) !== (int) $this->appointment_id || ($context['exam_order_id'] ?? null) !== (int) $this->exam_order_id
                || ($context['unit_id'] ?? null) !== (int) $this->unit_id || ! is_int($context['actor_id'] ?? null) || $context['actor_id'] < 1 || $context['actor_id'] !== (int) $this->created_by
                || ! is_string($context['occurred_at'] ?? null) || ! is_string($context['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $context['key_hash'])
                || $this->source !== 'appointment' || ! in_array($this->status, ['open', 'canceled', 'in_progress'], true) || $this->completed_at !== null) {
                throw new LogicException('Unsupported visit evidence.');
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $context['occurred_at']);
            if (! $at || $at->format('Y-m-d\TH:i:sP') !== $context['occurred_at'] || $at->getOffset() !== 0 || ! $at->equalTo($this->checked_in_at)) {
                throw new LogicException('Visit time differs from evidence.');
            }

            if ($this->status === 'in_progress') {
                if (ExecutionStartEvidence::validate($context['start'] ?? null, (int) $this->id)->lessThan($at)) {
                    throw new LogicException('Visit start predates arrival.');
                }
            } elseif (isset($context['start'])) {
                throw new LogicException('Unexpected visit start.');
            }

            $cancellation = $context['cancellation'] ?? null;
            if ($this->status === 'canceled') {
                if (! is_array($cancellation) || ($cancellation['from_status'] ?? null) !== 'open'
                    || ! is_int($cancellation['actor_id'] ?? null) || $cancellation['actor_id'] < 1
                    || ! is_string($cancellation['reason'] ?? null) || trim($cancellation['reason']) !== $cancellation['reason'] || $cancellation['reason'] === '' || mb_strlen($cancellation['reason']) > 1000
                    || ! is_string($cancellation['occurred_at'] ?? null)
                    || ! is_string($cancellation['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $cancellation['key_hash'])
                    || ! is_string($cancellation['request_hash'] ?? null) || ! hash_equals(hash('sha256', $cancellation['reason']), $cancellation['request_hash'])) {
                    throw new LogicException('Invalid visit cancellation evidence.');
                }
                $canceledAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $cancellation['occurred_at']);
                if (! $canceledAt || $canceledAt->format('Y-m-d\TH:i:sP') !== $cancellation['occurred_at'] || $canceledAt->getOffset() !== 0 || $canceledAt->lessThan($at)) {
                    throw new LogicException('Invalid visit cancellation time.');
                }
            } elseif (array_key_exists('cancellation', $context)) {
                throw new LogicException('Unexpected cancellation evidence.');
            }

            return $context;
        } catch (Throwable) {
            throw ValidationException::withMessages(['visit' => 'O contexto do atendimento está indisponível. Solicite revisão autorizada.']);
        }
    }

    public function attendanceSnapshot(): array
    {
        $evidence = $this->contextSnapshot();
        $appointment = $this->appointment;
        $order = $this->order;
        if ($appointment === null || $order === null) {
            throw ValidationException::withMessages(['visit' => 'Os vínculos do atendimento estão indisponíveis.']);
        }
        $booking = $appointment->contextSnapshot();
        $order->contextSnapshot();
        $event = $booking['check_in'] ?? [];
        if ($appointment->status !== 'checked_in' || (int) $appointment->patient_id !== (int) $this->patient_id
            || (int) $order->patient_id !== (int) $this->patient_id || (int) $appointment->unit_id !== (int) $this->unit_id
            || ($event['visit_public_id'] ?? null) !== $this->public_id || ($event['exam_order_id'] ?? null) !== (int) $this->exam_order_id
            || ($event['key_hash'] ?? null) !== $evidence['key_hash'] || ($event['actor_id'] ?? null) !== $evidence['actor_id']
            || ! $appointment->checked_in_at->equalTo($this->checked_in_at)) {
            throw ValidationException::withMessages(['visit' => 'A evidência da reserva diverge do atendimento. Solicite revisão autorizada.']);
        }

        return $evidence;
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Visits require an explicit clinical workflow.'));
        self::deleting(fn () => throw new LogicException('Visits cannot be deleted.'));
    }
}
