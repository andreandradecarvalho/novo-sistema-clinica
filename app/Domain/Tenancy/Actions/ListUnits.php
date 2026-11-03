<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUnits
{
    public function execute(): LengthAwarePaginator
    {
        $units = Unit::query()->select(['id', 'tenant_id', 'public_id', 'name', 'city', 'state', 'is_active'])->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('units', 'units_listed', null, Unit::class);

        return $units;
    }
}
