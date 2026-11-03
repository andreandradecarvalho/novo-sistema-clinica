<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Tenancy\Models\Unit;
use App\Http\Requests\Concerns\ValidatesLocation;
use Illuminate\Foundation\Http\FormRequest;

final class SaveUnitRequest extends FormRequest
{
    use ValidatesLocation;

    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit ? $this->user()->can('update', $unit) : $this->user()->can('create', Unit::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], ...$this->locationRules(),
            'is_active' => ['required', 'boolean'], 'tenant_id' => ['prohibited'],
            'public_id' => ['prohibited'], 'company_id' => ['prohibited'],
        ];
    }
}
