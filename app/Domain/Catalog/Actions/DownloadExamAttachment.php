<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Services\ExamAttachmentStorage;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Gate;

final class DownloadExamAttachment
{
    /** @return array{contents: string, extension: string, id: int} */
    public function execute(ExamAttachment $attachment): array
    {
        $attachment = ExamAttachment::query()->whereKey($attachment->getKey())->firstOrFail();
        Gate::authorize('download', $attachment);
        $bytes = app(ExamAttachmentStorage::class)->read($attachment);
        app(RegistryAudit::class)->record('catalog', 'exam_attachment_downloaded', $attachment, ExamAttachment::class);

        return ['contents' => $bytes, 'extension' => pathinfo($attachment->path, PATHINFO_EXTENSION), 'id' => (int) $attachment->id];
    }
}
