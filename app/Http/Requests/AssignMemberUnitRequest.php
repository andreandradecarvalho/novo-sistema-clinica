<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AssignMemberUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('user');

        return $subject instanceof User && $this->user()->can('assignUnit', $subject);
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['present', 'nullable', 'integer'],
            'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'], 'roles' => ['prohibited'],
            'permissions' => ['prohibited'], 'is_active' => ['prohibited'], 'password' => ['prohibited'],
        ];
    }
}
