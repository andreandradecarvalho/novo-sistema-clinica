<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Actions;

use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListProfessionals
{
    public function execute(): LengthAwarePaginator
    {
        $professionals = Professional::query()->select(['id', 'tenant_id', 'user_id', 'council', 'council_uf', 'council_number', 'specialty'])
            ->with('user:id,tenant_id,name,is_active')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('professionals', 'professionals_listed', null, Professional::class);

        return $professionals;
    }
}
