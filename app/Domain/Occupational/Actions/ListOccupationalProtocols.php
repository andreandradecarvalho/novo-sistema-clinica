<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListOccupationalProtocols
{
    public function execute(OccupationalProgram $program): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [OccupationalProtocol::class, $program]);
        $protocols = $program->protocols()->select(OccupationalProtocol::LIST_COLUMNS)->orderBy('code')->orderByDesc('version')->paginate(20);
        app(RegistryAudit::class)->record('occupational', 'occupational_protocols_listed', $program, OccupationalProgram::class);

        return $protocols;
    }
}
