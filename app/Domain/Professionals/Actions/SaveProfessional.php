<?php

declare(strict_types=1);

namespace App\Domain\Professionals\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SaveProfessional
{
    public function execute(array $input, ?Professional $professional = null): Professional
    {
        try {
            return DB::transaction(function () use ($input, $professional): Professional {
                $professional ??= new Professional;
                $event = $professional->exists ? 'professional_updated' : 'professional_created';
                if (! $professional->exists) {
                    $user = User::query()->whereKey($input['user_id'])->where('is_active', true)->lockForUpdate()->first();
                    if ($user === null) {
                        throw ValidationException::withMessages(['user_id' => 'Integrante indisponível nesta clínica.']);
                    }
                    $professional->user_id = $user->getKey();
                } elseif (isset($input['user_id']) && (int) $input['user_id'] !== (int) $professional->user_id) {
                    throw new LogicException('The professional user cannot change.');
                }
                $professional->fill(array_intersect_key($input, array_flip(['council', 'council_uf', 'council_number', 'specialty'])));
                $professional->save();
                app(RegistryAudit::class)->record('professionals', $event, $professional, Professional::class);

                return $professional;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['user_id' => 'Este integrante já possui cadastro profissional.']);
        }
    }
}
