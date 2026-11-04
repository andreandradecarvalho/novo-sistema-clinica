<?php

declare(strict_types=1);

namespace App\Domain\Patients\Services;

use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class PatientMergeGuard
{
    public function validate(Patient $source, Patient $survivor): void
    {
        if ($source->is($survivor)) {
            $this->refuse('Escolha dois cadastros diferentes.');
        }
        if ($source->cpf_hash !== null || $source->getRawOriginal('cpf_encrypted') !== null) {
            $this->refuse('A origem possui CPF. Escolha o cadastro identificado como sobrevivente; identidades conflitantes exigem revisão específica.');
        }
        if ($source->birth_date !== null && $survivor->birth_date !== null && $source->birth_date->toDateString() !== $survivor->birth_date->toDateString()) {
            $this->refuse('Os nascimentos divergem. Confira a identidade antes de solicitar merge.');
        }
        foreach (['user_id', 'company_id', 'company_unit_id', 'legacy_code', 'legacy_id', 'possible_duplicate_of_id', 'terms_accepted_at', 'terms_accepted_text', 'terms_accepted_ip', 'password', 'remember_token', 'portal_last_login_at'] as $field) {
            if ($source->getRawOriginal($field) !== null) {
                $this->refuse('A origem possui associação, origem legada ou histórico de acesso que este merge cadastral não pode transferir.');
            }
        }
        // Identifiers come only from the local database catalogue, never from HTTP input.
        $references = DB::table('information_schema.KEY_COLUMN_USAGE')->select(['TABLE_NAME', 'COLUMN_NAME'])
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('REFERENCED_TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('REFERENCED_TABLE_NAME', 'patients')->where('REFERENCED_COLUMN_NAME', 'id')->get();
        if ($references->isEmpty()) {
            $this->refuse('Não foi possível conferir as relações do cadastro.');
        }
        foreach ($references as $reference) {
            $table = $reference->TABLE_NAME;
            $column = $reference->COLUMN_NAME;
            if (! preg_match('/\A[a-z][a-z0-9_]*\z/', $table) || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $column) || ! Schema::hasColumn($table, 'tenant_id')) {
                $this->refuse('Há uma relação não suportada neste merge cadastral.');
            }
            if ($table === 'patient_company_links' && $column === 'patient_id') {
                continue;
            }
            if (DB::table($table)->where('tenant_id', app(TenantContext::class)->id())->where($column, $source->getKey())->lockForUpdate()->first([$column]) !== null) {
                $this->refuse('A origem possui vínculos ou histórico dependente. Este merge não move nem reescreve esses registros.');
            }
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['survivor_id' => $message]);
    }
}
