<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;

final class PrepareProfessionalRegistration
{
    public function execute(User $user): User
    {
        $candidate = User::query()->select(['id', 'public_id', 'name'])->whereKey($user->getKey())->where('is_active', true)
            ->whereNotIn('id', Professional::query()->select('user_id'))->firstOrFail();
        app(RegistryAudit::class)->record('professionals', 'professional_registration_viewed', $candidate, User::class);

        return $candidate;
    }
}
