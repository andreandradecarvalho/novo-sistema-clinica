<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;

final class ExamPreparationPolicy
{
    public function viewAny(User $actor, ExamType $examType): bool
    {
        return $actor->can('view', $examType);
    }

    public function create(User $actor, ExamType $examType): bool
    {
        return $actor->can('update', $examType);
    }

    public function view(User $actor, ExamPreparation $preparation): bool
    {
        return (int) $preparation->tenant_id === app(TenantContext::class)->id()
            && $preparation->examType !== null && $this->viewAny($actor, $preparation->examType);
    }

    public function update(User $actor, ExamPreparation $preparation): bool
    {
        $examType = $preparation->examType;

        return $examType !== null && $this->view($actor, $preparation) && $this->create($actor, $examType);
    }
}
