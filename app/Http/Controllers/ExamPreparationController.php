<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\ListExamPreparations;
use App\Domain\Catalog\Actions\SaveExamPreparation;
use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\SaveExamPreparationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class ExamPreparationController extends Controller
{
    public function index(ExamType $examType, ListExamPreparations $action): View
    {
        return view('catalog.preparations.index', ['examType' => $examType, 'preparations' => $action->execute($examType)]);
    }

    public function create(ExamType $examType): View
    {
        Gate::authorize('create', [ExamPreparation::class, $examType]);

        return view('catalog.preparations.form', ['examType' => $examType, 'preparation' => new ExamPreparation]);
    }

    public function store(SaveExamPreparationRequest $request, ExamType $examType, SaveExamPreparation $action): RedirectResponse
    {
        $preparation = $action->execute($examType, $request->validated());

        return redirect()->route('exam-types.preparations.show', [$examType->public_id, $preparation->id])->with('status', 'Preparo cadastrado.');
    }

    public function show(ExamType $examType, ExamPreparation $preparation): View
    {
        Gate::authorize('view', $preparation);
        app(RegistryAudit::class)->record('catalog', 'exam_preparation_viewed', $preparation, ExamPreparation::class);

        return view('catalog.preparations.show', compact('examType', 'preparation'));
    }

    public function edit(ExamType $examType, ExamPreparation $preparation): View
    {
        Gate::authorize('update', $preparation);
        app(RegistryAudit::class)->record('catalog', 'exam_preparation_edit_viewed', $preparation, ExamPreparation::class);

        return view('catalog.preparations.form', compact('examType', 'preparation'));
    }

    public function update(SaveExamPreparationRequest $request, ExamType $examType, ExamPreparation $preparation, SaveExamPreparation $action): RedirectResponse
    {
        $action->execute($examType, $request->validated(), $preparation);

        return redirect()->route('exam-types.preparations.show', [$examType->public_id, $preparation->id])->with('status', 'Preparo atualizado.');
    }
}
