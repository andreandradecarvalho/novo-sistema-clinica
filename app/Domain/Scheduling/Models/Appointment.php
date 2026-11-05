<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * @property CarbonImmutable $scheduled_date
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $canceled_at
 * @property string|null $creation_key_hash
 * @property string|null $creation_request_hash
 */
final class Appointment extends Model
{
    use BelongsToTenant, SoftDeletes;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted', 'creation_key_hash', 'creation_request_hash', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'immutable_date', 'canceled_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime', 'checked_in_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function contextSnapshot(): array
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant appointment evidence is forbidden.');
        }
        try {
            $context = json_decode(Crypt::decryptString((string) $this->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($context) || ($context['schema_version'] ?? null) !== 1 || ($context['tenant_id'] ?? null) !== (int) $this->tenant_id
                || ($context['public_id'] ?? null) !== $this->public_id || ($context['patient_id'] ?? null) !== (int) $this->patient_id
                || $this->unit_id === null || ($context['unit_id'] ?? null) !== (int) $this->unit_id
                || ($context['scheduled_date'] ?? null) !== $this->scheduled_date->format('Y-m-d') || $this->scheduled_at !== null || $this->deleted_at !== null
                || ($context['created_by'] ?? null) !== (int) $this->created_by || ! is_string($context['unit_name'] ?? null) || trim($context['unit_name']) === '') {
                throw new LogicException('Unsupported appointment snapshot.');
            }
            $confirmation = $context['confirmation'] ?? null;
            $checkIn = $context['check_in'] ?? null;
            if ($confirmation !== null) {
                $this->validateEvent($confirmation, $this->confirmed_at);
                if (($confirmation['source'] ?? null) !== 'staff') {
                    throw new LogicException('Unsupported confirmation source.');
                }
            } elseif ($this->confirmed_at !== null) {
                throw new LogicException('Missing confirmation evidence.');
            }
            if ($checkIn !== null) {
                $this->validateEvent($checkIn, $this->checked_in_at);
                if ($confirmation === null || ! is_string($checkIn['visit_public_id'] ?? null) || ! preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $checkIn['visit_public_id'])
                    || ! is_int($checkIn['exam_order_id'] ?? null) || $checkIn['exam_order_id'] < 1 || $this->checked_in_at->lessThan($this->confirmed_at)) {
                    throw new LogicException('Invalid check-in evidence.');
                }
            } elseif ($this->checked_in_at !== null) {
                throw new LogicException('Missing check-in evidence.');
            }
            $event = $context['cancellation'] ?? null;
            if ($event === null && $this->canceled_at === null
                && (($this->status === 'pre_agendado' && $confirmation === null)
                    || ($this->status === 'confirmado' && $confirmation !== null && $checkIn === null)
                    || ($this->status === 'checked_in' && $confirmation !== null && $checkIn !== null))) {
                return $context;
            }
            if ($this->status !== 'cancelado' || $this->canceled_at === null || $checkIn !== null
                || (($event['from_status'] ?? 'pre_agendado') !== ($confirmation === null ? 'pre_agendado' : 'confirmado')) || ! is_array($event) || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1
                || ! is_string($event['reason'] ?? null) || trim($event['reason']) === '' || mb_strlen($event['reason']) > 1000
                || ! is_string($event['occurred_at'] ?? null) || ! is_string($event['key_hash'] ?? null) || ! is_string($event['request_hash'] ?? null)
                || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash']) || ! hash_equals(hash('sha256', $event['reason']), $event['request_hash'])) {
                throw new LogicException('Unsupported appointment cancellation.');
            }
            $at = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
            if (! $at || $at->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || $at->getOffset() !== 0 || ! $at->equalTo($this->canceled_at)) {
                throw new LogicException('Invalid cancellation time.');
            }

            return $context;
        } catch (Throwable) {
            throw ValidationException::withMessages(['appointment' => 'O contexto da reserva está indisponível. Solicite revisão autorizada.']);
        }
    }

    private function validateEvent(mixed $event, ?CarbonImmutable $at): void
    {
        if (! is_array($event) || $at === null || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1
            || ! is_string($event['occurred_at'] ?? null) || ! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash'])) {
            throw new LogicException('Invalid appointment transition evidence.');
        }
        $time = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $event['occurred_at']);
        if (! $time || $time->format('Y-m-d\TH:i:sP') !== $event['occurred_at'] || $time->getOffset() !== 0 || ! $time->equalTo($at)) {
            throw new LogicException('Invalid appointment transition time.');
        }
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Use an authorized appointment transition.'));
        self::deleting(fn () => throw new LogicException('Appointments cannot be deleted.'));
    }
}
