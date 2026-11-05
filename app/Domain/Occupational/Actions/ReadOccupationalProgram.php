<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Actions;

use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use JsonException;

final class ReadOccupationalProgram
{
    public function execute(OccupationalProgram $program): array
    {
        $program = OccupationalProgram::query()->whereKey($program->getKey())->firstOrFail();
        Gate::authorize('view', $program);
        try {
            $context = json_decode(Crypt::decryptString((string) $program->getRawOriginal('context_encrypted')), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $context = null;
        }
        if (! is_array($context) || ($context['schema_version'] ?? null) !== 1 || ($context['program_version'] ?? null) !== $program->version
            || ($context['tenant_id'] ?? null) !== (int) $program->tenant_id || ($context['company_id'] ?? null) !== (int) $program->company_id
            || ($context['professional_id'] ?? null) !== (int) $program->responsible_professional_id) {
            throw (new ModelNotFoundException)->setModel(OccupationalProgram::class);
        }
        foreach (['company_name', 'professional_name', 'context'] as $field) {
            if (! is_string($context[$field] ?? null)) {
                throw (new ModelNotFoundException)->setModel(OccupationalProgram::class);
            }
        }
        foreach (['council', 'council_uf', 'council_number'] as $field) {
            if (! array_key_exists($field, $context) || ($context[$field] !== null && ! is_string($context[$field]))) {
                throw (new ModelNotFoundException)->setModel(OccupationalProgram::class);
            }
        }
        app(RegistryAudit::class)->record('occupational', 'occupational_program_viewed', $program, OccupationalProgram::class);

        return $context;
    }
}
