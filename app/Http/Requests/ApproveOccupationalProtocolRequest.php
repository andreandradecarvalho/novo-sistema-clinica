<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveOccupationalProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('protocol'));
    }

    public function rules(): array
    {
        return ['attested' => ['accepted'], 'approved_by' => ['prohibited'], 'approved_at' => ['prohibited'],
            'tenant_id' => ['prohibited'], 'program_id' => ['prohibited'], 'rules' => ['prohibited'], 'id' => ['prohibited'], 'version' => ['prohibited']];
    }
}
