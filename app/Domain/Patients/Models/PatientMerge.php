<?php

declare(strict_types=1);

namespace App\Domain\Patients\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PatientMerge extends Model
{
    use BelongsToTenant;

    #[\Override]
    public $timestamps = false;

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['payload_snapshot', 'reason'];

    protected function casts(): array
    {
        return ['payload_snapshot' => 'array', 'reason' => 'encrypted', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function mergedPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'merged_patient_id')->withTrashed();
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Patient merge decisions are immutable.'));
        self::deleting(fn () => throw new LogicException('Patient merge decisions cannot be deleted.'));
    }
}
