<?php

declare(strict_types=1);

namespace App\Domain\Occupational\Services;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use Illuminate\Validation\ValidationException;

final class ProtocolRules
{
    public function validateDates(OccupationalProgram $program, string $from, ?string $until): void
    {
        if (($until !== null && $until < $from) || ($program->valid_from !== null && $from < $program->valid_from->toDateString())
            || ($program->valid_until !== null && ($from > $program->valid_until->toDateString() || $until === null || $until > $program->valid_until->toDateString()))) {
            throw ValidationException::withMessages(['valid_until' => 'Informe um período dentro da vigência do programa.']);
        }
    }

    public function build(array $input): array
    {
        $lines = preg_split('/\R/u', trim($input['exam_codes'])) ?: [];
        $codes = array_map(ExamType::normalizeCode(...), $lines);
        if ($codes === [] || count($codes) > 100 || count(array_unique($codes)) !== count($codes)) {
            throw ValidationException::withMessages(['exam_codes' => 'Informe de 1 a 100 códigos diferentes, um por linha.']);
        }
        foreach ($codes as $code) {
            if (! preg_match('/\A[A-Z0-9_-]{1,20}\z/', $code)) {
                throw ValidationException::withMessages(['exam_codes' => 'Há um código de exame inválido. Confira o catálogo.']);
            }
        }
        $exams = ExamType::query()->select(['id', 'tenant_id', 'code', 'name'])->whereIn('code', $codes)->where('is_active', true)->where('scope', 'exame')->orderBy('id')->lockForUpdate()->get()->keyBy(fn (ExamType $exam): string => ExamType::normalizeCode($exam->code));
        if ($exams->count() !== count($codes)) {
            throw ValidationException::withMessages(['exam_codes' => 'Todos os exames devem existir e estar ativos no catálogo desta clínica.']);
        }

        return ['schema_version' => 1, 'source_reference' => $input['source_reference'], 'job_title' => $input['job_title'] ?? null,
            'risk_context' => $input['risk_context'], 'exams' => array_map(function (string $code) use ($exams): array {
                $exam = $exams->get($code);

                return ['exam_type_id' => (int) $exam->id, 'code' => $exam->code, 'name' => $exam->name];
            }, $codes)];
    }

    public function read(OccupationalProtocol $protocol): array
    {
        $rules = $protocol->getAttribute('rules');
        if (! is_array($rules) || count($rules) !== 5 || ($rules['schema_version'] ?? null) !== 1
            || ! is_string($rules['source_reference'] ?? null) || trim($rules['source_reference']) === '' || mb_strlen($rules['source_reference']) > 190
            || ! is_string($rules['risk_context'] ?? null) || trim($rules['risk_context']) === '' || mb_strlen($rules['risk_context']) > 4000
            || ! array_key_exists('job_title', $rules) || ($rules['job_title'] !== null && ! is_string($rules['job_title']))
            || ! is_array($rules['exams'] ?? null) || ! array_is_list($rules['exams']) || count($rules['exams']) < 1 || count($rules['exams']) > 100) {
            $this->invalid();
        }
        $ids = [];
        foreach ($rules['exams'] as $exam) {
            if (! is_array($exam) || count($exam) !== 3 || ! is_int($exam['exam_type_id'] ?? null) || $exam['exam_type_id'] < 1
                || ! is_string($exam['code'] ?? null) || ! is_string($exam['name'] ?? null) || in_array($exam['exam_type_id'], $ids, true)) {
                $this->invalid();
            }
            $ids[] = $exam['exam_type_id'];
        }

        return $rules;
    }

    public function verifyCatalogue(OccupationalProtocol $protocol): void
    {
        $rules = $this->read($protocol);
        $exams = ExamType::query()->select(['id', 'tenant_id', 'code', 'name'])->whereIn('id', array_column($rules['exams'], 'exam_type_id'))
            ->where('is_active', true)->where('scope', 'exame')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($rules['exams'] as $snapshot) {
            $exam = $exams->get($snapshot['exam_type_id']);
            if ($exam === null || $exam->code !== $snapshot['code'] || $exam->name !== $snapshot['name']) {
                throw ValidationException::withMessages(['approval' => 'O catálogo mudou ou há exame indisponível. Cadastre e revise uma nova versão.']);
            }
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['rules' => 'Esta estrutura de regras não tem suporte. Cadastre uma nova versão revisada.']);
    }
}
