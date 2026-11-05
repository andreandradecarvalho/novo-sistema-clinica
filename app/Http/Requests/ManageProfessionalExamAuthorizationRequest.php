<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use Illuminate\Foundation\Http\FormRequest;

final class ManageProfessionalExamAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', [ProfessionalExamAuthorization::class, $this->route('professional')]);
    }

    public function rules(): array
    {
        $rules = ['reason' => ['required', 'string', 'max:1000'],
            'exam_type_id' => $this->route('authorization') instanceof ProfessionalExamAuthorization ? ['prohibited'] : ['required', 'integer', 'min:1']];
        foreach (['id', 'tenant_id', 'professional_id', 'granted_by', 'granted_at', 'revoked_by', 'revoked_at', 'grant_reason_encrypted', 'revocation_reason_encrypted', 'active_flag', 'created_at', 'updated_at', 'permissions'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
