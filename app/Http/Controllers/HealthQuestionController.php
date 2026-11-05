<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\BuildHealthQuestionSnapshot;
use App\Domain\Catalog\Actions\CreateHealthQuestionGroup;
use App\Domain\Catalog\Actions\ListHealthQuestionGroups;
use App\Domain\Catalog\Actions\ListHealthQuestions;
use App\Domain\Catalog\Actions\PublishHealthQuestion;
use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\CreateHealthQuestionGroupRequest;
use App\Http\Requests\PublishHealthQuestionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class HealthQuestionController extends Controller
{
    public function index(ListHealthQuestionGroups $action): View
    {
        return view('questionnaires.index', ['groups' => $action->execute()]);
    }

    public function storeGroup(CreateHealthQuestionGroupRequest $request, CreateHealthQuestionGroup $action): RedirectResponse
    {
        $group = $action->execute($request->validated());

        return redirect()->route('questionnaires.show', $group->id)->with('status', 'Grupo cadastrado.');
    }

    public function show(HealthQuestionGroup $group, ListHealthQuestions $action): View
    {
        return view('questionnaires.show', ['group' => $group, 'questions' => $action->execute($group)]);
    }

    public function store(PublishHealthQuestionRequest $request, HealthQuestionGroup $group, PublishHealthQuestion $action): RedirectResponse
    {
        $question = $action->execute($group, $request->validated());

        return redirect()->route('questionnaires.questions.show', [$group->id, $question->id])->with('status', 'Versão da pergunta cadastrada.');
    }

    public function question(HealthQuestionGroup $group, HealthQuestion $question, BuildHealthQuestionSnapshot $action): View
    {
        $snapshot = $action->execute($group, $question);
        app(RegistryAudit::class)->record('catalog', 'health_question_version_viewed', $question, HealthQuestion::class);

        return view('questionnaires.question', compact('group', 'question', 'snapshot'));
    }

    public function revise(PublishHealthQuestionRequest $request, HealthQuestionGroup $group, HealthQuestion $question, PublishHealthQuestion $action): RedirectResponse
    {
        $version = $action->execute($group, $request->validated(), $question);

        return redirect()->route('questionnaires.questions.show', [$group->id, $version->id])->with('status', 'Nova versão cadastrada. A anterior foi preservada.');
    }
}
