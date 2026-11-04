<?php

declare(strict_types=1);

namespace App\Domain\Patients\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Patients\Services\PatientMergeGuard;
use App\Domain\Patients\Services\PatientMergeReview;
use App\Domain\Shared\Services\RegistryAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MergePatients
{
    public function execute(User $actor, Patient $patient, Patient $candidate, array $input): PatientMerge
    {
        return DB::transaction(function () use ($actor, $patient, $candidate, $input): PatientMerge {
            // Consistent ordering serializes overlapping merges and patient edits.
            $patients = Patient::query()->whereKey([$patient->getKey(), $candidate->getKey()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $first = $patients->get($patient->getKey());
            $second = $patients->get($candidate->getKey());
            if (! $first instanceof Patient || ! $second instanceof Patient) {
                throw ValidationException::withMessages(['survivor_id' => 'Um cadastro está indisponível. Abra a comparação novamente.']);
            }
            Gate::forUser($actor)->authorize('merge', [$first, $second]);
            app(PatientMergeReview::class)->validate($input['review_token'], $actor, $first, $second, true);
            $survivor = $patients->get((int) $input['survivor_id']);
            if (! $survivor instanceof Patient) {
                throw ValidationException::withMessages(['survivor_id' => 'Escolha um dos dois cadastros comparados.']);
            }
            $source = $first->is($survivor) ? $second : $first;
            app(PatientMergeGuard::class)->validate($source, $survivor);
            $merge = new PatientMerge;
            $merge->forceFill([
                'survivor_patient_id' => $survivor->getKey(), 'merged_patient_id' => $source->getKey(), 'merged_by' => $actor->getKey(),
                'reason' => $input['reason'], 'created_at' => now(),
                'payload_snapshot' => ['version' => 2, 'strategy' => 'preserve_employment_ownership',
                    'source_encrypted' => Crypt::encryptString(json_encode(app(PatientMergeReview::class)->snapshot($source, true), JSON_THROW_ON_ERROR)),
                    'survivor_encrypted' => Crypt::encryptString(json_encode(app(PatientMergeReview::class)->snapshot($survivor, true), JSON_THROW_ON_ERROR))],
            ])->save();
            $source->delete();
            app(RegistryAudit::class)->record('patients', 'patients_merged', $merge, PatientMerge::class);

            return $merge;
        });
    }
}
