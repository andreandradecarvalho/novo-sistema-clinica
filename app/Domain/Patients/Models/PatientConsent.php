<?php

declare(strict_types=1);

namespace App\Domain\Patients\Models;

use App\Domain\Patients\Enums\ConsentDecision;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use LogicException;

/** @property array|null $evidence_encrypted */
final class PatientConsent extends Model
{
    use BelongsToTenant;

    public const array LIST_COLUMNS = ['id', 'tenant_id', 'patient_id', 'purpose', 'legal_basis', 'policy_version', 'decision', 'occurred_at', 'recorded_by', 'source'];

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['evidence_encrypted'];

    protected function casts(): array
    {
        return ['decision' => ConsentDecision::class, 'occurred_at' => 'immutable_datetime', 'evidence_encrypted' => 'encrypted:array'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @throws DecryptException|JsonException */
    public function evidence(): ?array
    {
        $ciphertext = $this->getRawOriginal('evidence_encrypted');
        if (! is_string($ciphertext)) {
            return null;
        }
        $evidence = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);

        return is_array($evidence) ? $evidence : null;
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Consent events are immutable. Record a new event.'));
        self::deleting(fn () => throw new LogicException('Consent events cannot be deleted.'));
    }
}
