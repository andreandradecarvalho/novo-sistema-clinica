<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Models;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable|null $termination_date
 */
final class EmploymentLink extends Model
{
    use BelongsToTenant;

    public const array REVIEW_COLUMNS = ['id', 'tenant_id', 'patient_id', 'company_id', 'company_unit_id', 'registration_number', 'job_title', 'department', 'starts_on', 'ends_on', 'termination_date', 'source', 'context_encrypted', 'created_at', 'updated_at'];

    #[\Override]
    protected $table = 'patient_company_links';

    #[\Override]
    protected $guarded = ['*'];

    #[\Override]
    protected $hidden = ['context_encrypted'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'termination_date' => 'immutable_date', 'context_encrypted' => 'encrypted:array'];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /** @return BelongsTo<CompanyUnit, $this> */
    public function companyUnit(): BelongsTo
    {
        return $this->belongsTo(CompanyUnit::class);
    }

    protected static function booted(): void
    {
        self::updating(function (EmploymentLink $link): void {
            if ($link->getRawOriginal('ends_on') !== null || array_diff(array_keys($link->getDirty()), ['ends_on', 'termination_date', 'updated_at']) !== []) {
                throw new LogicException('Employment history cannot be overwritten; create a new version.');
            }
        });
        self::deleting(fn () => throw new LogicException('Employment history cannot be deleted.'));
    }
}
