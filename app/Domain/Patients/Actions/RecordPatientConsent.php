<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Patients\Enums\ConsentDecision;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RecordPatientConsent
{
    public function execute(Patient $patient, array $input): PatientConsent
    {
        return DB::transaction(function () use ($patient, $input): PatientConsent {
            $patient = Patient::query()->whereKey($patient->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('create', [PatientConsent::class, $patient]);
            $consent = new PatientConsent;
            $consent->patient()->associate($patient);
            $consent->forceFill([
                'purpose' => $input['purpose'], 'legal_basis' => $input['legal_basis'],
                'policy_version' => $input['policy_version'], 'policy_text_hash' => hash('sha256', $input['policy_text']),
                'decision' => ConsentDecision::from($input['decision']), 'occurred_at' => now('UTC'),
                'recorded_by' => auth()->id(), 'source' => 'staff_recorded',
                'evidence_encrypted' => ['version' => 1, 'tenant_id' => (int) $patient->tenant_id, 'patient_id' => (int) $patient->getKey(), 'policy_text' => $input['policy_text'], 'evidence_note' => $input['evidence_note']],
            ])->save();
            app(RegistryAudit::class)->record('consents', 'patient_consent_recorded', $consent, PatientConsent::class);

            return $consent;
        });
    }
}
