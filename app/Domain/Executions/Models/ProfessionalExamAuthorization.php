<?php

declare(strict_types=1);

namespace App\Domain\Executions\Models;

use App\Domain\Catalog\Models\ExamType;
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
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $revoked_at
 */
final class ProfessionalExamAuthorization extends Model
{
    use BelongsToTenant;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['grant_reason_encrypted', 'revocation_reason_encrypted'];

    protected function casts(): array
    {
        return ['granted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ExamType, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function reasonSnapshot(): array
    {
        $tenantId = app(TenantContext::class)->id();
        if ((int) $this->tenant_id !== $tenantId || (int) $this->getRawOriginal('tenant_id') !== $tenantId) {
            throw new LogicException('Cross-tenant authorization evidence is forbidden.');
        }
        try {
            $grant = Crypt::decryptString((string) $this->getRawOriginal('grant_reason_encrypted'));
            $revoke = $this->revoked_at === null ? null : Crypt::decryptString((string) $this->getRawOriginal('revocation_reason_encrypted'));
            foreach ([$grant, $revoke] as $reason) {
                if ($reason !== null && ($reason === '' || trim($reason) !== $reason || mb_strlen($reason) > 1000)) {
                    throw new LogicException('Invalid authorization reason.');
                }
            }

            return ['grant' => $grant, 'revocation' => $revoke];
        } catch (Throwable) {
            throw ValidationException::withMessages(['authorization' => 'A evidência da concessão está indisponível. Solicite revisão autorizada.']);
        }
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Use the authorization revocation workflow.'));
        self::deleting(fn () => throw new LogicException('Authorization history cannot be deleted.'));
    }
}
