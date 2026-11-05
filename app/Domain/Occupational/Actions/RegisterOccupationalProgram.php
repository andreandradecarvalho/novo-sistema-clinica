<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RegisterOccupationalProgram
{
    public function execute(Company $company, Professional $professional, array $input): OccupationalProgram
    {
        try {
            return DB::transaction(function () use ($company, $professional, $input): OccupationalProgram {
                $company = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('create', [OccupationalProgram::class, $company]);
                if (! $company->is_active) {
                    throw ValidationException::withMessages(['version' => 'A empresa precisa estar ativa para registrar uma nova versão.']);
                }
                $professional = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('view', $professional);
                $user = $professional->user()->where('is_active', true)->lockForUpdate()->firstOrFail();
                $program = new OccupationalProgram;
                $program->company()->associate($company);
                $program->forceFill([
                    'version' => $input['version'], 'valid_from' => $input['valid_from'], 'valid_until' => $input['valid_until'] ?? null,
                    'source_reference' => $input['source_reference'], 'responsible_professional_id' => $professional->id,
                    'context_encrypted' => [
                        'schema_version' => 1, 'tenant_id' => (int) $company->tenant_id, 'company_id' => (int) $company->id,
                        'professional_id' => (int) $professional->id, 'program_version' => $input['version'], 'company_name' => $company->name,
                        'professional_name' => $user->name, 'council' => $professional->council, 'council_uf' => $professional->council_uf,
                        'council_number' => $professional->council_number, 'context' => $input['context'],
                    ],
                ])->save();
                app(RegistryAudit::class)->record('occupational', 'occupational_program_registered', $program, OccupationalProgram::class);

                return $program;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['version' => 'Já existe esta versão para a empresa nesta clínica.']);
        }
    }
}
