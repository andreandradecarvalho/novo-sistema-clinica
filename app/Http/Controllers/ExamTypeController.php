<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\ListExamTypes;
use App\Domain\Catalog\Actions\SaveExamType;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\SaveExamTypeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ExamTypeController extends Controller
{
    public function index(ListExamTypes $action): View
    {
        return view('catalog.index', ['exams' => $action->execute()]);
    }

    public function create(): View
    {
        Gate::authorize('create', ExamType::class);

        return view('catalog.form', ['examType' => new ExamType]);
    }

    public function store(SaveExamTypeRequest $request, SaveExamType $action): RedirectResponse
    {
        $examType = $action->execute($request->validated());

        return redirect()->route('exam-types.show', $examType->public_id)->with('status', 'Exame cadastrado.');
    }

    public function show(ExamType $examType): View
    {
        Gate::authorize('view', $examType);
        app(RegistryAudit::class)->record('catalog', 'exam_type_viewed', $examType, ExamType::class);

        return view('catalog.show', ['examType' => $examType]);
    }

    public function edit(ExamType $examType): View
    {
        Gate::authorize('update', $examType);
        app(RegistryAudit::class)->record('catalog', 'exam_type_edit_viewed', $examType, ExamType::class);

        return view('catalog.form', ['examType' => $examType]);
    }

    public function update(SaveExamTypeRequest $request, ExamType $examType, SaveExamType $action): RedirectResponse
    {
        $action->execute($request->validated(), $examType);

        return redirect()->route('exam-types.show', $examType->public_id)->with('status', 'Exame atualizado.');
    }
}
