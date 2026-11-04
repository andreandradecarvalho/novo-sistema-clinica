<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use JsonException;

final class ReadPatientConsentEvidence
{
    public function execute(PatientConsent $consent): array
    {
        $consent = PatientConsent::query()->whereKey($consent->getKey())->firstOrFail();
        Gate::authorize('view', $consent);
        try {
            $evidence = $consent->evidence();
        } catch (DecryptException|JsonException) {
            $evidence = null;
        }
        if (! is_array($evidence) || ($evidence['version'] ?? null) !== 1
            || ($evidence['tenant_id'] ?? null) !== (int) $consent->tenant_id || ($evidence['patient_id'] ?? null) !== (int) $consent->patient_id || ! is_string($evidence['policy_text'] ?? null)
            || ! is_string($evidence['evidence_note'] ?? null) || ! hash_equals((string) $consent->policy_text_hash, hash('sha256', $evidence['policy_text']))) {
            throw (new ModelNotFoundException)->setModel(PatientConsent::class);
        }
        app(RegistryAudit::class)->record('consents', 'patient_consent_evidence_viewed', $consent, PatientConsent::class);

        return $evidence;
    }
}
