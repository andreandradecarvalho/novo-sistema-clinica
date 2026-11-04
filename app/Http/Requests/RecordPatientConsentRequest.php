<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Patients\Enums\ConsentDecision;
use App\Domain\Patients\Models\PatientConsent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPatientConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [PatientConsent::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'max:80'],
            'legal_basis' => ['required', 'string', 'max:80'],
            'policy_version' => ['required', 'string', 'max:40'],
            'policy_text' => ['required', 'string', 'max:16000'],
            'evidence_note' => ['required', 'string', 'max:4000'],
            'decision' => ['required', Rule::enum(ConsentDecision::class)],
            'attested' => ['accepted'],
            'tenant_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'id' => ['prohibited'],
            'recorded_by' => ['prohibited'], 'source' => ['prohibited'], 'occurred_at' => ['prohibited'],
            'policy_text_hash' => ['prohibited'], 'evidence_encrypted' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }
}
