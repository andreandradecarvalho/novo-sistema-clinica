<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Occupational\Services\ProtocolRules;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateOccupationalProtocol
{
    public function execute(OccupationalProgram $program, array $input, ?OccupationalProtocol $source = null): OccupationalProtocol
    {
        try {
            return DB::transaction(function () use ($program, $input, $source): OccupationalProtocol {
                $program = OccupationalProgram::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('create', [OccupationalProtocol::class, $program]);
                $company = $program->company()->lockForUpdate()->firstOrFail();
                if (! $company->is_active) {
                    throw ValidationException::withMessages(['code' => 'A empresa precisa estar ativa para cadastrar protocolos.']);
                }
                $version = 1;
                $code = Str::upper(trim($input['code'] ?? ''));
                if ($source !== null) {
                    $source = $program->protocols()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
                    $latest = $program->protocols()->where('code', $source->code)->orderByDesc('version')->lockForUpdate()->firstOrFail();
                    if ($latest->id !== $source->id || $source->version >= 65535) {
                        throw ValidationException::withMessages(['code' => 'Utilize a versão mais recente; o limite de versões não pode ser excedido.']);
                    }
                    $code = $source->code;
                    $version = $source->version + 1;
                }
                $rules = app(ProtocolRules::class);
                $rules->validateDates($program, $input['valid_from'], $input['valid_until'] ?? null);
                $protocol = new OccupationalProtocol;
                $protocol->program()->associate($program);
                $protocol->forceFill(['code' => $code, 'version' => $version, 'valid_from' => $input['valid_from'], 'valid_until' => $input['valid_until'] ?? null, 'rules' => $rules->build($input)])->save();
                app(RegistryAudit::class)->record('occupational', 'occupational_protocol_created', $protocol, OccupationalProtocol::class);

                return $protocol;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Este código já identifica um protocolo da clínica. Cadastre uma nova versão na origem.']);
        }
    }
}
