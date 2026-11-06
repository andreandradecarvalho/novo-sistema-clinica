<?php

declare(strict_types=1);

namespace App\Domain\Executions\Models;

use App\Domain\Executions\Services\ExecutionCompletionEvidence;
use App\Domain\Executions\Services\ExecutionExceptionEvidence;
use App\Domain\Executions\Services\ExecutionRetryEvidence;
use App\Domain\Executions\Services\ExecutionStartEvidence;
use App\Domain\Orders\Models\ExamOrderItem;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class ExamExecution extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted', 'reason_encrypted', 'creation_key_hash', 'creation_request_hash'];

    protected function casts(): array
    {
        return ['attempt_no' => 'integer', 'created_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Visit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /** @return BelongsTo<ExamOrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ExamOrderItem::class, 'exam_order_item_id');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function contextSnapshot(): array
    {
        if ((int) $this->tenant_id !== app(TenantContext::class)->id()) {
            throw new LogicException('Cross-tenant execution evidence is forbidden.');
        }
        try {
            $context = json_decode(Crypt::decryptString((string) $this->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($context) || ($context['schema_version'] ?? null) !== 1
                || ($context['tenant_id'] ?? null) !== (int) $this->tenant_id || ($context['visit_id'] ?? null) !== (int) $this->visit_id
                || $this->exam_order_item_id === null || ($context['item_id'] ?? null) !== (int) $this->exam_order_item_id
                || ($context['exam_type_id'] ?? null) !== (int) $this->exam_type_id || ($context['attempt_no'] ?? null) !== $this->attempt_no
                || ! is_int($context['actor_id'] ?? null) || $context['actor_id'] < 1 || $context['actor_id'] !== (int) $this->created_by
                || ! is_string($context['occurred_at'] ?? null) || $this->attempt_no < 1
                || ! is_string($context['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $context['key_hash'])
                || $context['key_hash'] !== $this->creation_key_hash || ! is_string($context['request_hash'] ?? null)
                || ! preg_match('/\A[a-f0-9]{64}\z/', $context['request_hash']) || $context['request_hash'] !== $this->creation_request_hash
                || $this->source !== 'order'
                || ! in_array($this->status, ['pending', 'canceled', 'in_progress', 'completed', 'refused', 'incomplete'], true)) {
                throw new LogicException('Unsupported execution evidence.');
            }
            $at = self::utc($context['occurred_at']);
            if ($this->created_at === null || ! $at->equalTo($this->created_at)
                || ! hash_equals(self::requestHash((int) $this->visit_id, (int) $this->exam_order_item_id), $context['request_hash'])) {
                throw new LogicException('Execution preparation differs from evidence.');
            }
            $assignedAt = $at;
            if (array_key_exists('room_assignment', $context)) {
                $assignment = $context['room_assignment'];
                if (! is_array($assignment) || $this->room_id === null || ($assignment['room_id'] ?? null) !== (int) $this->room_id
                    || ! is_int($assignment['unit_id'] ?? null) || $assignment['unit_id'] < 1
                    || ! is_int($assignment['actor_id'] ?? null) || $assignment['actor_id'] < 1
                    || ! is_string($assignment['room_key'] ?? null) || $assignment['room_key'] === ''
                    || ! is_string($assignment['room_name'] ?? null) || $assignment['room_name'] === ''
                    || ! is_string($assignment['occurred_at'] ?? null) || ! is_string($assignment['key_hash'] ?? null)
                    || ! preg_match('/\A[a-f0-9]{64}\z/', $assignment['key_hash'])) {
                    throw new LogicException('Invalid room assignment evidence.');
                }
                $assignedAt = self::utc($assignment['occurred_at']);
                if ($assignedAt->lessThan($at)) {
                    throw new LogicException('Room assignment predates preparation.');
                }
            } elseif ($this->room_id !== null) {
                throw new LogicException('Missing room assignment evidence.');
            }
            if (in_array($this->status, ['in_progress', 'completed', 'refused', 'incomplete'], true)) {
                $startedAt = ExecutionStartEvidence::validate($context['start'] ?? null, (int) $this->visit_id);
                if (($context['start']['execution_id'] ?? null) !== (int) $this->id || ! isset($context['room_assignment']) || $startedAt->lessThan($assignedAt)) {
                    throw new LogicException('Invalid execution start sequence.');
                }
            } elseif (isset($context['start']) || $this->performed_by !== null || $this->started_at !== null) {
                throw new LogicException('Unexpected execution start.');
            }
            if ($this->status === 'completed') {
                ExecutionCompletionEvidence::validate($this, $context);
            } elseif (isset($context['completion']) || ($this->finished_at !== null && ! in_array($this->status, ['refused', 'incomplete'], true))) {
                throw new LogicException('Unexpected execution completion.');
            }
            if (in_array($this->status, ['refused', 'incomplete'], true)) {
                ExecutionExceptionEvidence::validate($this, $context);
                $context['exception']['reason'] = Crypt::decryptString((string) $this->getRawOriginal('reason_encrypted'));
            } elseif (array_key_exists('exception', $context)) {
                throw new LogicException('Unexpected execution exception.');
            }
            if ($this->status === 'canceled') {
                $event = $context['cancellation'] ?? null;
                if (! is_array($event) || ($event['from_status'] ?? null) !== 'pending' || ! is_int($event['actor_id'] ?? null) || $event['actor_id'] < 1
                    || ! is_string($event['occurred_at'] ?? null) || ! is_string($event['key_hash'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $event['key_hash'])
                    || ! is_string($event['request_hash'] ?? null) || self::utc($event['occurred_at'])->lessThan($assignedAt)) {
                    throw new LogicException('Invalid execution cancellation evidence.');
                }
                $reason = Crypt::decryptString((string) $this->getRawOriginal('reason_encrypted'));
                if ($reason === '' || trim($reason) !== $reason || mb_strlen($reason) > 1000 || ! hash_equals(hash('sha256', $reason), $event['request_hash'])) {
                    throw new LogicException('Invalid cancellation reason.');
                }
                $context['cancellation']['reason'] = $reason;
            } elseif (array_key_exists('cancellation', $context) || ($this->reason_encrypted !== null && ! in_array($this->status, ['refused', 'incomplete'], true))) {
                throw new LogicException('Unexpected execution cancellation.');
            }

            ExecutionRetryEvidence::validate($this, $context);

            return $context;
        } catch (Throwable) {
            throw ValidationException::withMessages(['execution' => 'A evidência da tentativa está indisponível. Solicite revisão autorizada.']);
        }
    }

    public function preparationSnapshot(): array
    {
        $context = $this->contextSnapshot();
        $visit = $this->visit;
        $item = $this->item;
        if ($visit === null || $item === null || (int) $item->exam_order_id !== (int) $visit->exam_order_id || (int) $item->exam_type_id !== (int) $this->exam_type_id
            || ($visit->status === 'canceled' && $this->status !== 'canceled')) {
            throw ValidationException::withMessages(['execution' => 'Os vínculos da tentativa divergem do atendimento.']);
        }
        if (isset($context['room_assignment'])) {
            $room = $this->room;
            if ($room === null || (int) $room->unit_id !== (int) $visit->unit_id || $context['room_assignment']['unit_id'] !== (int) $visit->unit_id) {
                throw ValidationException::withMessages(['execution' => 'A sala atribuída diverge da unidade do atendimento.']);
            }
        }
        $visit->attendanceSnapshot();
        if (in_array($this->status, ['in_progress', 'completed', 'refused', 'incomplete'], true) && (! in_array($visit->status, ['in_progress', 'completed'], true) || ! in_array($visit->order?->status, ['in_progress', 'completed'], true))) {
            throw ValidationException::withMessages(['execution' => 'A execução diverge da situação do item, pedido ou atendimento.']);
        }

        return $context;
    }

    public static function requestHash(int $visitId, int $itemId): string
    {
        return hash('sha256', json_encode(['visit_id' => $visitId, 'item_id' => $itemId], JSON_THROW_ON_ERROR));
    }

    private static function utc(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $value);
        if (! $date || $date->format('Y-m-d\TH:i:sP') !== $value || $date->getOffset() !== 0) {
            throw new LogicException('Invalid UTC time.');
        }

        return $date;
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Executions require explicit workflow actions.'));
        self::deleting(fn () => throw new LogicException('Execution attempts cannot be deleted.'));
    }
}
