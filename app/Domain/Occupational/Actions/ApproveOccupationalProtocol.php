<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Occupational\Services\ProtocolRules;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveOccupationalProtocol
{
    public function execute(OccupationalProgram $program, OccupationalProtocol $protocol): OccupationalProtocol
    {
        return DB::transaction(function () use ($program, $protocol): OccupationalProtocol {
            $program = OccupationalProgram::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $company = $program->company()->lockForUpdate()->firstOrFail();
            $protocol = $program->protocols()->whereKey($protocol->getKey())->lockForUpdate()->firstOrFail();
            $professional = Professional::query()->where('user_id', auth()->id())->lockForUpdate()->first();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('approve', $protocol);
            if (! $company->is_active || $protocol->approved_at !== null || $protocol->approved_by !== null || $professional === null) {
                throw ValidationException::withMessages(['approval' => 'A versão já foi aprovada ou o contexto não permite aprovação.']);
            }
            $latest = $program->protocols()->where('code', $protocol->code)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            if ($protocol->valid_from === null || $latest->id !== $protocol->id) {
                throw ValidationException::withMessages(['approval' => 'Revise a versão mais recente e informe sua vigência antes de aprovar.']);
            }
            $rules = app(ProtocolRules::class);
            $rules->validateDates($program, $protocol->valid_from->toDateString(), $protocol->valid_until?->toDateString());
            $rules->verifyCatalogue($protocol);
            // The only mutable fields; identity, context and pending state are all revalidated here.
            $changed = DB::table('occupational_protocols')->where('tenant_id', app(TenantContext::class)->id())->where('id', $protocol->id)
                ->whereNull('approved_by')->whereNull('approved_at')->update(['approved_by' => $professional->id, 'approved_at' => now('UTC'), 'updated_at' => now('UTC')]);
            if ($changed !== 1) {
                throw ValidationException::withMessages(['approval' => 'A versão mudou. Reabra a consulta.']);
            }
            $protocol->refresh();
            app(RegistryAudit::class)->record('occupational', 'occupational_protocol_approved', $protocol, OccupationalProtocol::class);

            return $protocol;
        });
    }
}
