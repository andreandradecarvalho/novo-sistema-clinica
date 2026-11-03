<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListProfessionalCandidates
{
    public function execute(): LengthAwarePaginator
    {
        $users = User::query()->select(['id', 'public_id', 'name'])->where('is_active', true)
            ->whereNotIn('id', Professional::query()->select('user_id'))->orderBy('name')->orderBy('id')->paginate(20);
        app(RegistryAudit::class)->record('professionals', 'professional_candidates_listed', null, Professional::class);

        return $users;
    }
}
