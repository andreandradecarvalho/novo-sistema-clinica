<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class ExamOrder extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted'];

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
