<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\DownloadExamAttachment;
use App\Domain\Catalog\Actions\ListExamAttachments;
use App\Domain\Catalog\Actions\UploadExamAttachment;
use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\ValueObjects\AttachmentContents;
use App\Http\Requests\UploadExamAttachmentRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ExamAttachmentController extends Controller
{
    public function index(ExamType $examType, ListExamAttachments $action): View
    {
        return view('catalog.attachments.index', ['examType' => $examType, 'attachments' => $action->execute($examType)]);
    }

    public function create(ExamType $examType): View
    {
        Gate::authorize('create', [ExamAttachment::class, $examType]);

        return view('catalog.attachments.form', compact('examType'));
    }

    public function store(UploadExamAttachmentRequest $request, ExamType $examType, UploadExamAttachment $action): RedirectResponse
    {
        $action->execute($examType, $request->validated('label'), new AttachmentContents($request->file('file')->getContent()));

        return redirect()->route('exam-types.attachments.index', $examType->public_id)->with('status', 'Anexo de orientação cadastrado.');
    }

    public function download(ExamType $examType, ExamAttachment $attachment, DownloadExamAttachment $action): Response
    {
        $file = $action->execute($attachment);

        return response($file['contents'], 200, [
            'Content-Type' => match ($file['extension']) {
                'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', default => abort(404),
            },
            'Content-Disposition' => 'attachment; filename="orientacao-'.$file['id'].'.'.$file['extension'].'"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
