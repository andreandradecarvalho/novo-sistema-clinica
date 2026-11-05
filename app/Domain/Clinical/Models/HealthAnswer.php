<?php

declare(strict_types=1);

namespace App\Domain\Clinical\Models;

use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class HealthAnswer extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['answer_encrypted'];

    #[\Override]
    public $timestamps = false;

    protected function casts(): array
    {
        return ['question_options_snapshot' => 'array'];
    }

    /** @return BelongsTo<HealthRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }

    /** @return BelongsTo<HealthQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(HealthQuestion::class, 'question_id');
    }

    public function snapshot(): array
    {
        $tenant = app(TenantContext::class)->id();
        if ((int) $this->tenant_id !== $tenant || (int) $this->getRawOriginal('tenant_id') !== $tenant) {
            throw new LogicException('Cross-tenant answer evidence is forbidden.');
        }
        try {
            $data = json_decode(Crypt::decryptString((string) $this->getRawOriginal('answer_encrypted')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['schema_version'] ?? null) !== 1 || ($data['tenant_id'] ?? null) !== $tenant
                || ($data['record_id'] ?? null) !== (int) $this->health_record_id || ($data['question_id'] ?? null) !== (int) $this->question_id
                || ! is_string($data['code'] ?? null) || $data['code'] === '' || ! is_int($data['version'] ?? null) || $data['version'] < 1
                || ($data['label'] ?? null) !== $this->question_label_snapshot || ($data['type'] ?? null) !== 'text' || $this->question_type_snapshot !== 'text'
                || ($data['options'] ?? null) !== null || $this->question_options_snapshot !== null
                || ! is_string($data['answer'] ?? null) || $data['answer'] === '' || trim($data['answer']) !== $data['answer'] || mb_strlen($data['answer']) > 10000) {
                throw new LogicException('Invalid answer evidence.');
            }

            return $data;
        } catch (Throwable) {
            throw ValidationException::withMessages(['health_record' => 'A resposta está indisponível. Solicite revisão clínica autorizada.']);
        }
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Clinical answers are immutable.'));
        self::deleting(fn () => throw new LogicException('Clinical answers cannot be deleted.'));
    }
}
