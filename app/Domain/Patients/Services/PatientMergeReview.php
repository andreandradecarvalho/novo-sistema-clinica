<?php

declare(strict_types=1);

namespace App\Domain\Patients\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use App\Domain\Tenancy\Services\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class PatientMergeReview
{
    public function issue(User $actor, Patient $patient, Patient $candidate): string
    {
        return Crypt::encryptString(json_encode([
            'version' => 2, 'tenant_id' => app(TenantContext::class)->id(), 'actor_id' => (int) $actor->getKey(),
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
            'patients' => [(string) $patient->getKey() => $this->fingerprint($patient), (string) $candidate->getKey() => $this->fingerprint($candidate)],
        ], JSON_THROW_ON_ERROR));
    }

    public function validate(string $token, User $actor, Patient $patient, Patient $candidate, bool $lockHistory = false): void
    {
        try {
            $review = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $review = null;
        }
        if (! is_array($review) || ($review['version'] ?? null) !== 2 || ($review['tenant_id'] ?? null) !== app(TenantContext::class)->id()
            || ($review['actor_id'] ?? null) !== (int) $actor->getKey() || ($review['expires_at'] ?? 0) <= now()->getTimestamp()
            || ($review['patients'][(string) $patient->getKey()] ?? null) !== $this->fingerprint($patient, $lockHistory)
            || ($review['patients'][(string) $candidate->getKey()] ?? null) !== $this->fingerprint($candidate, $lockHistory)) {
            throw ValidationException::withMessages(['review_token' => 'A comparação expirou ou os cadastros mudaram. Abra a comparação novamente.']);
        }
    }

    public function snapshot(Patient $patient, bool $lock = false): array
    {
        $ids = app(PatientIdentityGroup::class)->memberIds($patient, $lock);
        $links = EmploymentLink::query()->select(EmploymentLink::REVIEW_COLUMNS)->whereIn('patient_id', $ids)->orderBy('id');
        if ($lock) {
            $links->lockForUpdate();
        }

        return ['patient' => $patient->getRawOriginal(), 'member_ids' => $ids,
            'employments' => $links->get()->map(fn (EmploymentLink $link) => $link->getRawOriginal())->all()];
    }

    private function fingerprint(Patient $patient, bool $lock = false): string
    {
        return hash('sha256', json_encode($this->snapshot($patient, $lock), JSON_THROW_ON_ERROR));
    }
}
