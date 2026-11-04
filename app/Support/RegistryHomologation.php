<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientMerge;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class RegistryHomologation
{
    private array $fixtures = [];

    public function __construct(private readonly Closure $request, private readonly Closure $verify, private readonly Closure $token) {}

    public function run(array $clinics): void
    {
        app(LocalHomologation::class)->ensure();
        $run = strtolower((string) Str::ulid());
        $cpf = SyntheticCpf::generate();
        $results = [];
        try {
            foreach ($clinics as $clinic => [$tenant, $cookies]) {
                $results[$clinic] = $this->exercise($clinic, $tenant, $cookies, $run, $cpf);
            }
            $this->crossCheck($results['A'], $results['B']);
        } finally {
            $this->cleanup();
        }
    }

    private function remember(Tenant $tenant, string $table, int $id): void
    {
        $this->fixtures[(int) $tenant->id][$table][] = $id;
    }

    private function exercise(string $clinic, Tenant $tenant, CookieJar $cookies, string $run, string $cpf): array
    {
        $host = 'clinica-'.strtolower($clinic).'.localhost';
        $label = 'Etapa 2 '.$clinic.': ';
        $request = fn (string $method, string $path, array $data = []) => ($this->request)($host, $cookies, $method, $path, $data);
        $csrf = ($this->token)($request('GET', '/'));
        ($this->verify)($request('POST', '/user/confirm-password', ['_token' => $csrf, 'password' => config('homologation.clinics.'.$clinic.'.admin_password')])->successful(), $label.'senha confirmada');
        $post = fn (string $path, array $data) => $request('POST', $path, ['_token' => $csrf] + $data);
        $context = app(TenantContext::class);
        $name = 'Fixture sintética '.$run.' '.$clinic;
        $response = $post('/units', ['name' => $name, 'is_active' => '1']);
        $unit = $context->run($tenant, fn () => Unit::query()->where('name', $name)->first());
        if ($unit !== null) {
            $this->remember($tenant, 'units', (int) $unit->id);
        }
        ($this->verify)($response->status() === 302 && $unit !== null, $label.'unidade clínica criada por HTTP');
        $member = $context->run($tenant, fn () => User::query()->create(['name' => $name, 'email' => $run.'-'.$clinic.'@example.test', 'password' => Str::random(40)]));
        $this->remember($tenant, 'users', (int) $member->id);
        $response = $post('/users/'.$member->public_id.'/unit', ['_method' => 'PUT', 'unit_id' => $unit->id]);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => (int) $member->fresh()->unit_id === (int) $unit->id), $label.'atribuição de unidade por HTTP');
        $response = $post('/professionals', ['user_id' => $member->id, 'council' => 'CRM', 'council_uf' => 'MG', 'council_number' => 'SINT-'.substr($run, -10)]);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => Professional::query()->where('user_id', $member->id)->exists()), $label.'perfil profissional criado sem concessão clínica');
        $response = $post('/companies', ['name' => $name, 'is_active' => '1']);
        $company = $context->run($tenant, fn () => Company::query()->where('name', $name)->first());
        if ($company !== null) {
            $this->remember($tenant, 'companies', (int) $company->id);
        }
        ($this->verify)($response->status() === 302 && $company !== null, $label.'empresa criada por HTTP');
        $response = $post('/companies/'.$company->public_id.'/units', ['name' => $name, 'city' => 'Cidade sintética', 'state' => 'MG', 'is_active' => '1']);
        $companyUnit = $context->run($tenant, fn () => $company->units()->first());
        ($this->verify)($response->status() === 302 && $companyUnit !== null, $label.'estabelecimento do empregador criado por HTTP');
        $patients = [];
        foreach (['root', 'source'] as $kind) {
            $email = $run.'-'.$clinic.'-'.$kind.'@example.test';
            $data = ['name' => $name, 'birth_date' => '1990-01-02', 'email' => $email];
            if ($kind === 'root') {
                $data['cpf'] = $cpf;
            }
            $response = $post('/patients', $data);
            $patient = $context->run($tenant, fn () => Patient::query()->where('email', $email)->first());
            if ($patient !== null) {
                $this->remember($tenant, 'patients', (int) $patient->id);
            }
            ($this->verify)($response->status() === 302 && $patient !== null, $label.'paciente '.$kind.' criado por HTTP');
            $patients[$kind] = $patient;
        }
        ['root' => $root, 'source' => $source] = $patients;
        $response = $post('/patients/search', ['mode' => 'cpf', 'cpf' => $cpf]);
        $page = $request('GET', '/patients/search/results');
        ($this->verify)($response->status() === 302 && $page->ok() && str_contains($page->body(), $root->public_id) && ! str_contains($page->body(), $source->public_id) && ! str_contains($page->body(), $cpf), $label.'busca CPF/HMAC sem número na resposta');
        $response = $post('/patients/'.$source->public_id.'/employments/register/'.$company->public_id, ['starts_on' => '2026-01-01', 'job_title' => 'Função sintética inicial', 'company_unit_id' => $companyUnit->id]);
        $link = $context->run($tenant, fn () => EmploymentLink::query()->where('patient_id', $source->id)->first());
        ($this->verify)($response->status() === 302 && $link !== null, $label.'vínculo empregatício criado por HTTP');
        $pair = '/patients/'.$root->public_id.'/compare/'.$source->public_id;
        $page = $request('GET', $pair);
        if (! preg_match('/name="review_token"\s+value="([^"]+)"/', $page->body(), $matches)) {
            throw new RuntimeException('Synthetic comparison token unavailable.');
        }
        $before = $context->run($tenant, fn () => $link->fresh()->getRawOriginal());
        $response = $post($pair.'/merge', ['survivor_id' => $root->id, 'identity_confirmed' => '1', 'reason' => 'Conferência sintética da homologação local.', 'review_token' => html_entity_decode($matches[1], ENT_QUOTES)]);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => PatientMerge::query()->where('survivor_patient_id', $root->id)->where('merged_patient_id', $source->id)->exists() && $link->fresh()->getRawOriginal() === $before), $label.'merge humano preserva integralmente o vínculo');
        ($this->verify)($request('GET', '/patients/'.$source->public_id)->status() === 404, $label.'origem arquivada não abre por rota normal');
        $path = '/patients/'.$root->public_id.'/employments';
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), 'Função sintética inicial') && str_contains($page->body(), 'Origem preservada'), $label.'histórico consolidado com proveniência');
        $response = $post($path.'/'.$link->id.'/version', ['starts_on' => '2026-03-01', 'job_title' => 'Função sintética revisada']);
        $next = $context->run($tenant, fn () => EmploymentLink::query()->where('patient_id', $source->id)->orderByDesc('id')->firstOrFail());
        ($this->verify)($response->status() === 302 && (int) $next->id !== (int) $link->id && (int) $next->patient_id === (int) $source->id && (int) $next->company_id === (int) $company->id, $label.'versionamento pelo sobrevivente conserva origem/empregador');
        $response = $post($path.'/'.$next->id.'/terminate', ['termination_date' => '2026-06-30']);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => $next->fresh()->termination_date?->toDateString() === '2026-06-30'), $label.'desligamento pelo sobrevivente');
        $response = $post('/companies/'.$company->public_id.'/accesses', ['user_id' => $member->id, 'scope' => 'occupational_status', 'valid_from' => '2026-01-01T09:00']);
        $access = $context->run($tenant, fn () => CompanyAccess::query()->where('company_id', $company->id)->where('user_id', $member->id)->first());
        ($this->verify)($response->status() === 302 && $access !== null, $label.'concessão empresarial criada por HTTP');
        $response = $post('/companies/'.$company->public_id.'/accesses/'.$access->id.'/revoke', []);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => $access->fresh()->revoked_at !== null), $label.'concessão empresarial revogada por HTTP');
        ($this->verify)($context->run($tenant, fn () => $member->fresh()->roles->isEmpty() && $member->fresh()->permissions->isEmpty()), $label.'cadastros não concederam papéis/permissões ao integrante');

        return compact('tenant', 'cookies', 'host', 'root', 'source', 'company', 'link', 'csrf');
    }

    private function crossCheck(array $a, array $b): void
    {
        foreach ([[$a, $b, 'A'], [$b, $a, 'B']] as [$own, $other, $clinic]) {
            $response = ($this->request)($own['host'], $own['cookies'], 'GET', '/patients/'.$other['root']->public_id);
            ($this->verify)($response->status() === 404, 'Etapa 2 '.$clinic.': paciente da outra clínica recusado');
            $response = ($this->request)($own['host'], $own['cookies'], 'GET', '/companies/'.$other['company']->public_id.'/accesses');
            ($this->verify)($response->status() === 404, 'Etapa 2 '.$clinic.': concessões de outra empresa/tenant recusadas');
            $response = ($this->request)($own['host'], $own['cookies'], 'POST', '/patients/'.$own['root']->public_id.'/employments/'.$other['link']->id.'/terminate', ['_token' => $own['csrf'], 'termination_date' => '2026-06-30']);
            ($this->verify)($response->status() === 404, 'Etapa 2 '.$clinic.': vínculo cruzado recusado pelo binding');
            $response = ($this->request)($own['host'], $own['cookies'], 'POST', '/patients/search', ['_token' => $own['csrf'], 'mode' => 'name', 'name' => 'Fixture sintética']);
            $page = ($this->request)($own['host'], $own['cookies'], 'GET', '/patients/search/results');
            ($this->verify)($response->status() === 302 && $page->ok() && str_contains($page->body(), $own['root']->public_id) && ! str_contains($page->body(), $other['root']->public_id), 'Etapa 2 '.$clinic.': busca por nome não cruza clínicas');
        }
    }

    private function cleanup(): void
    {
        app(LocalHomologation::class)->ensure();
        DB::transaction(function (): void {
            foreach ($this->fixtures as $tenantId => $fixture) {
                $patients = $fixture['patients'] ?? [];
                $companies = $fixture['companies'] ?? [];
                $users = $fixture['users'] ?? [];
                DB::table('patient_merges')->where('tenant_id', $tenantId)->whereIn('survivor_patient_id', $patients)->whereIn('merged_patient_id', $patients)->delete();
                DB::table('patient_company_links')->where('tenant_id', $tenantId)->whereIn('patient_id', $patients)->delete();
                DB::table('patients')->where('tenant_id', $tenantId)->whereIn('id', $patients)->delete();
                DB::table('company_user_access')->where('tenant_id', $tenantId)->whereIn('company_id', $companies)->whereIn('user_id', $users)->delete();
                DB::table('company_units')->where('tenant_id', $tenantId)->whereIn('company_id', $companies)->delete();
                DB::table('companies')->where('tenant_id', $tenantId)->whereIn('id', $companies)->delete();
                DB::table('professionals')->where('tenant_id', $tenantId)->whereIn('user_id', $users)->delete();
                DB::table('sessions')->where('tenant_id', $tenantId)->whereIn('user_id', $users)->delete();
                DB::table('users')->where('tenant_id', $tenantId)->whereIn('id', $users)->delete();
                DB::table('units')->where('tenant_id', $tenantId)->whereIn('id', $fixture['units'] ?? [])->delete();
                foreach (['patients' => $patients, 'companies' => $companies, 'users' => $users, 'units' => $fixture['units'] ?? []] as $table => $ids) {
                    if (DB::table($table)->where('tenant_id', $tenantId)->whereIn('id', $ids)->exists()) {
                        throw new RuntimeException('Synthetic registry cleanup incomplete.');
                    }
                }
            }
        });
        ($this->verify)(true, 'Etapa 2: fixtures temporárias removidas; auditoria operacional preservada');
    }
}
