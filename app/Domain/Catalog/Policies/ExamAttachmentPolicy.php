<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Services\TenantContext;

final class ExamAttachmentPolicy
{
    public function viewAny(User $actor, ExamType $examType): bool
    {
        return $actor->can('view', $examType);
    }

    public function create(User $actor, ExamType $examType): bool
    {
        return $actor->can('update', $examType);
    }

    public function download(User $actor, ExamAttachment $attachment): bool
    {
        return (int) $attachment->tenant_id === app(TenantContext::class)->id()
            && $attachment->examType !== null && $this->viewAny($actor, $attachment->examType);
    }
}
