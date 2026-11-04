<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\ValueObjects\AttachmentContents;
use App\Domain\Tenancy\Services\TenantStorage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ExamAttachmentStorage
{
    public function newPath(int $examTypeId, AttachmentContents $file): string
    {
        return 'catalog/exams/'.$examTypeId.'/attachments/'.Str::ulid().'.'.$file->extension;
    }

    public function put(string $path, AttachmentContents $file): void
    {
        if (! app(TenantStorage::class)->put($path, Crypt::encryptString($file->bytes))) {
            throw new RuntimeException('Private attachment storage failed.');
        }
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete(app(TenantStorage::class)->path($path));
    }

    public function read(ExamAttachment $attachment): string
    {
        $pattern = '~\Acatalog/exams/'.(int) $attachment->exam_type_id.'/attachments/[0-9A-HJKMNP-TV-Z]{26}\.(pdf|jpg|png)\z~D';
        if ($attachment->disk !== 'local' || ! preg_match($pattern, (string) $attachment->path)) {
            $this->unavailable();
        }
        $contents = app(TenantStorage::class)->get($attachment->path);
        if ($contents === null) {
            $this->unavailable();
        }
        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException) {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw new ModelNotFoundException;
    }
}
