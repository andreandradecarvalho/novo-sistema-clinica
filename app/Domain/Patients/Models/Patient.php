<?php

declare(strict_types=1);

namespace App\Domain\Patients\Models;

use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** @property bool $is_partial */
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

    protected static function booted(): void
    {
        self::creating(function (Patient $patient): void {
            $patient->public_id ??= (string) Str::ulid();
        });
        self::saving(function (Patient $patient): void {
            $patient->normalized_name = Str::lower(Str::ascii(Str::squish($patient->name)));
        });
    }
}
