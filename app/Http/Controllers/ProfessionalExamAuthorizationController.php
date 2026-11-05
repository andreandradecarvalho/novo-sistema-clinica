<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Actions\ListProfessionalExamAuthorizations;
use App\Domain\Executions\Actions\ManageProfessionalExamAuthorization;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Professionals\Models\Professional;
use App\Http\Requests\ManageProfessionalExamAuthorizationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ProfessionalExamAuthorizationController extends Controller
{
    public function index(Professional $professional, ListProfessionalExamAuthorizations $action): View
    {
        return view('professionals.exam-authorizations', $action->execute($professional));
    }

    public function store(ManageProfessionalExamAuthorizationRequest $request, Professional $professional, ManageProfessionalExamAuthorization $action): RedirectResponse
    {
        $action->grant($professional, ExamType::query()->whereKey($request->validated('exam_type_id'))->firstOrFail(), $request->validated('reason'));

        return redirect()->route('professionals.authorizations.index', $professional->id)->with('status', 'Concessão de exame registrada.');
    }

    public function revoke(ManageProfessionalExamAuthorizationRequest $request, Professional $professional, ProfessionalExamAuthorization $authorization, ManageProfessionalExamAuthorization $action): RedirectResponse
    {
        $action->revoke($professional, $authorization, $request->validated('reason'));

        return redirect()->route('professionals.authorizations.index', $professional->id)->with('status', 'Revogação de concessão registrada.');
    }
}
