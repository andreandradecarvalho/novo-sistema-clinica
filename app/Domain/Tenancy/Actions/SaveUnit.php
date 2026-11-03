<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Models\Unit;
use Illuminate\Support\Facades\DB;

final class SaveUnit
{
    public function execute(array $input, ?Unit $unit = null): Unit
    {
        return DB::transaction(function () use ($input, $unit): Unit {
            $unit ??= new Unit;
            $event = $unit->exists ? 'unit_updated' : 'unit_created';
            $unit->fill(array_intersect_key($input, array_flip(['name', 'city', 'state', 'is_active'])));
            $unit->save();
            app(RegistryAudit::class)->record('units', $event, $unit, Unit::class);

            return $unit;
        });
    }
}
