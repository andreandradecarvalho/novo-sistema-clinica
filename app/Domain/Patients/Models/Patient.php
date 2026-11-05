<?php

declare(strict_types=1);

namespace App\Domain\Patients\Models;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Services\PatientIdentity;
use App\Domain\Patients\Services\PatientIdentityGroup;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * @property bool $is_partial
 * @property Carbon|null $birth_date
 */
final class Patient extends Model
{
    use BelongsToTenant, SoftDeletes;

    #[\Override]
    protected $fillable = ['name', 'birth_date', 'email', 'mobile_phone'];

    #[\Override]
    protected $hidden = ['cpf_encrypted', 'cpf_hash', 'password', 'remember_token'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'is_partial' => 'boolean', 'cpf_encrypted' => 'encrypted'];
    }

    /** @return HasMany<EmploymentLink, $this> */
    public function employments(): HasMany
    {
        return $this->hasMany(EmploymentLink::class, 'patient_id');
    }

    /** @return HasMany<PatientConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    /** @return HasMany<ExamOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(ExamOrder::class);
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        if ($childType === 'employment') {
            if ($field !== null && $field !== 'id') {
                throw new LogicException('Unsupported employment binding key.');
            }

            return EmploymentLink::query()->whereIn('patient_id', app(PatientIdentityGroup::class)->memberIds($this))->whereKey($value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    protected static function booted(): void
    {
        self::creating(function (Patient $patient): void {
            $patient->public_id ??= (string) Str::ulid();
        });
        self::updating(function (Patient $patient): void {
            if ($patient->getRawOriginal('deleted_at') !== null && PatientMerge::query()->where('merged_patient_id', $patient->getKey())->exists()) {
                throw new LogicException('Archived merge origins cannot be changed or restored.');
            }
        });
        self::saving(function (Patient $patient): void {
            $patient->normalized_name = PatientIdentity::normalizeName($patient->name);
        });
    }
}
