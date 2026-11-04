<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListExamAttachments
{
    public function execute(ExamType $examType): LengthAwarePaginator
    {
        Gate::authorize('viewAny', [ExamAttachment::class, $examType]);
        $attachments = $examType->attachments()->select(['id', 'exam_type_id', 'tenant_id', 'label', 'created_at'])->orderByDesc('id')->paginate(20);
        app(RegistryAudit::class)->record('catalog', 'exam_attachments_listed', $examType, ExamType::class);

        return $attachments;
    }
}
