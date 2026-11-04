<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\Services\ExamAttachmentStorage;
use App\Domain\Catalog\ValueObjects\AttachmentContents;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class UploadExamAttachment
{
    public function __construct(private ExamAttachmentStorage $storage) {}

    public function execute(ExamType $examType, string $label, AttachmentContents $file): ExamAttachment
    {
        $path = null;
        try {
            return DB::transaction(function () use ($examType, $label, $file, &$path): ExamAttachment {
                $examType = ExamType::query()->whereKey($examType->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('create', [ExamAttachment::class, $examType]);
                $path = $this->storage->newPath((int) $examType->id, $file);
                $this->storage->put($path, $file);
                $attachment = new ExamAttachment(['label' => $label]);
                $attachment->examType()->associate($examType);
                $attachment->forceFill(['disk' => 'local', 'path' => $path])->save();
                app(RegistryAudit::class)->record('catalog', 'exam_attachment_uploaded', $attachment, ExamAttachment::class);

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                try {
                    if (! $this->storage->delete($path)) {
                        Log::error('Private attachment cleanup failed.');
                    }
                } catch (Throwable) {
                    Log::error('Private attachment cleanup failed.');
                }
            }
            throw $exception;
        }
    }
}
