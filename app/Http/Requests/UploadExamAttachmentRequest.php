<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\ValueObjects\AttachmentContents;
use Illuminate\Foundation\Http\FormRequest;

final class UploadExamAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ExamAttachment::class, $this->route('exam_type')]);
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:150'], 'file' => ['required', 'file', 'mimetypes:'.implode(',', array_keys(AttachmentContents::MIME_EXTENSIONS)), 'extensions:pdf,jpg,jpeg,png', 'max:'.(AttachmentContents::MAX_BYTES / 1024)],
            'tenant_id' => ['prohibited'], 'exam_type_id' => ['prohibited'], 'id' => ['prohibited'], 'disk' => ['prohibited'], 'path' => ['prohibited']];
    }
}
