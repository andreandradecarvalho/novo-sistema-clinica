<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MergePatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');
        $candidate = $this->route('candidate');

        return $patient instanceof Patient && $candidate instanceof Patient && $this->user()->can('merge', [$patient, $candidate]);
    }

    public function rules(): array
    {
        return [
            'survivor_id' => ['required', 'integer', Rule::in([$this->route('patient')->getKey(), $this->route('candidate')->getKey()])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'], 'identity_confirmed' => ['required', 'accepted'],
            'review_token' => ['required', 'string', 'max:4096'],
            'tenant_id' => ['prohibited'], 'merged_by' => ['prohibited'], 'payload_snapshot' => ['prohibited'], 'cpf' => ['prohibited'],
        ];
    }
}
