<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Occupational\Actions\ApproveOccupationalProtocol;
use App\Domain\Occupational\Actions\CreateOccupationalProtocol;
use App\Domain\Occupational\Actions\ListOccupationalProtocols;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Occupational\Services\ProtocolRules;
use App\Domain\Shared\Services\RegistryAudit;
use App\Http\Requests\ApproveOccupationalProtocolRequest;
use App\Http\Requests\SaveOccupationalProtocolRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class OccupationalProtocolController extends Controller
{
    public function index(OccupationalProgram $program, ListOccupationalProtocols $action): View
    {
        return view('occupational.protocols.index', ['program' => $program, 'protocols' => $action->execute($program)]);
    }

    public function create(OccupationalProgram $program): View
    {
        Gate::authorize('create', [OccupationalProtocol::class, $program]);

        return view('occupational.protocols.form', ['program' => $program, 'protocol' => null, 'rules' => []]);
    }

    public function store(SaveOccupationalProtocolRequest $request, OccupationalProgram $program, CreateOccupationalProtocol $action): RedirectResponse
    {
        $protocol = $action->execute($program, $request->validated());

        return redirect()->route('programs.protocols.show', [$program->id, $protocol->id])->with('status', 'Versão cadastrada, aguardando aprovação.');
    }

    public function show(OccupationalProgram $program, OccupationalProtocol $protocol, ProtocolRules $rules): View
    {
        Gate::authorize('view', $protocol);
        $definition = $rules->read($protocol);
        $protocol->load(['approver:id,tenant_id,user_id', 'approver.user:id,tenant_id,name']);
        app(RegistryAudit::class)->record('occupational', 'occupational_protocol_viewed', $protocol, OccupationalProtocol::class);

        return view('occupational.protocols.show', ['program' => $program, 'protocol' => $protocol, 'rules' => $definition]);
    }

    public function version(OccupationalProgram $program, OccupationalProtocol $protocol, ProtocolRules $rules): View
    {
        Gate::authorize('create', [OccupationalProtocol::class, $program]);
        app(RegistryAudit::class)->record('occupational', 'occupational_protocol_version_form_viewed', $protocol, OccupationalProtocol::class);

        return view('occupational.protocols.form', ['program' => $program, 'protocol' => $protocol, 'rules' => $rules->read($protocol)]);
    }

    public function replace(SaveOccupationalProtocolRequest $request, OccupationalProgram $program, OccupationalProtocol $protocol, CreateOccupationalProtocol $action): RedirectResponse
    {
        $version = $action->execute($program, $request->validated(), $protocol);

        return redirect()->route('programs.protocols.show', [$program->id, $version->id])->with('status', 'Nova versão cadastrada, aguardando aprovação própria.');
    }

    public function approve(ApproveOccupationalProtocolRequest $request, OccupationalProgram $program, OccupationalProtocol $protocol, ApproveOccupationalProtocol $action): RedirectResponse
    {
        $action->execute($program, $protocol);

        return redirect()->route('programs.protocols.show', [$program->id, $protocol->id])->with('status', 'Aprovação registrada para esta versão.');
    }
}
