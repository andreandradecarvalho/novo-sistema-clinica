<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Models\Professional;

final class ProtocolApprover
{
    public function find(User $actor): ?Professional
    {
        $query = Professional::query()->where('user_id', $actor->getKey())->where('council', 'CRM')
            ->whereNotNull('council_number')->where('council_number', '<>', '')->whereNotNull('council_uf')
            ->whereHas('user', fn ($query) => $query->where('is_active', true));
        $professional = $query->first();

        return $professional !== null && preg_match('/\A[A-Z]{2}\z/', (string) $professional->council_uf) ? $professional : null;
    }
}
