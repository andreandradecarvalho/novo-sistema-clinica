<?php

declare(strict_types=1);

namespace App\Domain\Executions\Actions;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ManageProfessionalExamAuthorization
{
    public function grant(Professional $professional, ExamType $exam, string $reason): ProfessionalExamAuthorization
    {
        $reason = $this->reason($reason);
        try {
            return DB::transaction(function () use ($professional, $exam, $reason): ProfessionalExamAuthorization {
                $professional = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
                $exam = ExamType::query()->whereKey($exam->getKey())->lockForUpdate()->firstOrFail();
                $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('manage', [ProfessionalExamAuthorization::class, $professional]);
                if (! $exam->is_active || ! User::query()->whereKey($professional->user_id)->where('is_active', true)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['authorization' => 'Profissional ou exame inativo nesta clínica.']);
                }
                $grant = new ProfessionalExamAuthorization;
                $grant->forceFill(['professional_id' => $professional->id, 'exam_type_id' => $exam->id, 'granted_by' => $actor->id,
                    'granted_at' => now('UTC')->startOfSecond(), 'grant_reason_encrypted' => Crypt::encryptString($reason)])->save();
                app(RegistryAudit::class)->record('professional_exam_authorizations', 'professional_exam_authorized', $grant, ProfessionalExamAuthorization::class);

                return $grant;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['authorization' => 'Já existe uma concessão vigente para este profissional e exame.']);
        }
    }

    public function revoke(Professional $professional, ProfessionalExamAuthorization $grant, string $reason): ProfessionalExamAuthorization
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($professional, $grant, $reason): ProfessionalExamAuthorization {
            $professional = Professional::query()->whereKey($professional->getKey())->lockForUpdate()->firstOrFail();
            $actor = User::query()->whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', [ProfessionalExamAuthorization::class, $professional]);
            $grant = ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            $history = $grant->reasonSnapshot();
            if ($grant->revoked_at !== null) {
                if ((int) $grant->revoked_by !== (int) $actor->id || $history['revocation'] !== $reason) {
                    throw ValidationException::withMessages(['authorization' => 'A concessão já foi revogada com outro registro.']);
                }

                return $grant;
            }
            $now = now('UTC')->startOfSecond();
            if ($now->lessThan($grant->granted_at)) {
                throw ValidationException::withMessages(['authorization' => 'O instante da revogação antecede a concessão.']);
            }
            // Exact tenant/professional/grant/current-state update; no ownership or original-grant changes.
            DB::table('professional_exam_authorizations')->where('tenant_id', $grant->tenant_id)->where('professional_id', $professional->id)->where('id', $grant->id)
                ->whereNull('revoked_at')->update(['revoked_by' => $actor->id, 'revoked_at' => $now, 'revocation_reason_encrypted' => Crypt::encryptString($reason), 'updated_at' => $now]);
            $grant->refresh();
            app(RegistryAudit::class)->record('professional_exam_authorizations', 'professional_exam_authorization_revoked', $grant, ProfessionalExamAuthorization::class);

            return $grant;
        }, 3);
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return $reason;
    }
}
