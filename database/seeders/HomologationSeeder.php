<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Actions\ProvisionTenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Support\LocalHomologation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class HomologationSeeder extends Seeder
{
    public function run(): void
    {
        app(LocalHomologation::class)->ensure();
        foreach (['A', 'B'] as $clinic) {
            $password = config('homologation.clinics.'.$clinic.'.admin_password');
            $receptionPassword = config('homologation.clinics.'.$clinic.'.reception_password');
            $secret = config('homologation.clinics.'.$clinic.'.totp_secret');
            if (! is_string($password) || strlen($password) < 16 || ! is_string($receptionPassword) || strlen($receptionPassword) < 16 || ! is_string($secret) || ! preg_match('/^[A-Z2-7]{32}$/', $secret)) {
                throw new RuntimeException('Configure private homologation credentials first.');
            }
            $tenant = app(ProvisionTenant::class)->execute([
                'name' => 'Clínica sintética '.$clinic, 'slug' => 'homologacao-'.strtolower($clinic), 'domain' => 'clinica-'.strtolower($clinic).'.localhost',
            ]);
            app(TenantContext::class)->run($tenant, function () use ($clinic, $password, $receptionPassword, $secret): void {
                DB::transaction(function () use ($clinic, $password, $receptionPassword, $secret): void {
                    $admin = User::query()->firstOrCreate(['email' => 'admin.homologacao@example.test'], ['name' => 'Administrador sintético '.$clinic, 'password' => $password]);
                    if ($admin->wasRecentlyCreated) {
                        $admin->forceFill([
                            'must_change_password' => false,
                            'two_factor_secret' => encrypt($secret),
                            'two_factor_recovery_codes' => encrypt(json_encode(array_map(fn () => Str::random(20), range(1, 8)), JSON_THROW_ON_ERROR)),
                            'two_factor_confirmed_at' => now(),
                        ])->save();
                        $admin->assignRole('clinic_admin');
                    }
                    $reception = User::query()->firstOrCreate(['email' => 'recepcao.homologacao@example.test'], ['name' => 'Recepção sintética '.$clinic, 'password' => $receptionPassword]);
                    if ($reception->wasRecentlyCreated) {
                        $reception->forceFill(['must_change_password' => $clinic === 'A'])->save();
                        $reception->assignRole('receptionist');
                    }
                });
            });
        }
    }
}
