<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalog\Actions\BuildHealthQuestionSnapshot;
use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\Models\HealthQuestion;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Catalog\Services\ExamAttachmentStorage;
use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Tenancy\Services\TenantStorage;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class StageThreeHomologation
{
    private const array TABLES = ['exam_types', 'exam_type_preparations', 'exam_type_attachments', 'health_question_groups', 'health_questions', 'patient_consents', 'patients', 'companies', 'users', 'professionals', 'occupational_programs', 'occupational_protocols'];

    private array $fixtures = [];

    private array $files = [];

    public function __construct(private readonly Closure $request, private readonly Closure $verify, private readonly Closure $token) {}

    public function run(array $clinics): void
    {
        app(LocalHomologation::class)->ensure();
        $results = [];
        $run = (string) Str::ulid();
        try {
            foreach ($clinics as $clinic => [$tenant, $cookies]) {
                $results[$clinic] = $this->exercise($clinic, $tenant, $cookies, $run);
            }
            $this->crossCheck($results['A'], $results['B']);
        } finally {
            $this->cleanup();
        }
    }

    private function remember(Tenant $tenant, string $table, int $id): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Unsupported synthetic fixture table.');
        }
        $this->fixtures[(int) $tenant->id][$table][] = $id;
    }

    private function exercise(string $clinic, Tenant $tenant, CookieJar $cookies, string $run): array
    {
        $context = app(TenantContext::class);
        $host = 'clinica-'.strtolower($clinic).'.localhost';
        $request = fn (string $method, string $path, array $data = [], ?array $file = null) => ($this->request)($host, $cookies, $method, $path, $data, [], $file);
        $label = 'Etapa 3 '.$clinic.': ';
        $csrf = ($this->token)($request('GET', '/'));
        $post = fn (string $path, array $data = [], ?array $file = null) => $request('POST', $path, ['_token' => $csrf] + $data, $file);
        ($this->verify)($post('/user/confirm-password', ['password' => config('homologation.clinics.'.$clinic.'.admin_password')])->successful(), $label.'senha administrativa confirmada');
        $name = 'Fixture etapa 3 '.$run.' '.$clinic;
        $code = 'S3-'.substr($run, -12).$clinic;
        $examInput = ['code' => $code, 'name' => $name, 'requires_fasting' => '0', 'default_duration_min' => '15', 'price' => '25.00', 'ordem' => '0', 'is_active' => '1'];
        $response = $post('/exam-types', $examInput);
        $exam = $context->run($tenant, fn () => ExamType::query()->where('code', $code)->first());
        if ($exam !== null) {
            $this->remember($tenant, 'exam_types', (int) $exam->id);
        }
        ($this->verify)($response->status() === 302 && $exam !== null, $label.'catálogo criado por HTTP');
        $examPath = '/exam-types/'.$exam->public_id;
        ($this->verify)($request('GET', $examPath)->ok(), $label.'catálogo consultável');
        $response = $post($examPath, ['_method' => 'PUT', 'price' => '27.50'] + $examInput);
        ($this->verify)($response->status() === 302 && $context->run($tenant, fn () => $exam->fresh()->price === '27.50'), $label.'preço de referência atualizado por HTTP');
        $response = $post($examPath.'/preparations', ['title' => $name, 'instructions' => 'Orientação sintética sem conteúdo médico.']);
        $preparation = $context->run($tenant, fn () => ExamPreparation::query()->where('exam_type_id', $exam->id)->first());
        if ($preparation !== null) {
            $this->remember($tenant, 'exam_type_preparations', (int) $preparation->id);
        }
        ($this->verify)($response->status() === 302 && $preparation !== null, $label.'preparo textual criado por HTTP');
        $response = $post($examPath.'/preparations/'.$preparation->id, ['_method' => 'PUT', 'title' => $name, 'instructions' => 'Orientação sintética revisada.']);
        ($this->verify)($response->status() === 302 && str_contains($request('GET', $examPath.'/preparations/'.$preparation->id)->body(), 'Orientação sintética revisada.'), $label.'preparo revisado sem atravessar exame');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmS8AAAAASUVORK5CYII=', true);
        $response = $post($examPath.'/attachments', ['label' => $name], ['field' => 'file', 'contents' => $png, 'name' => 'synthetic.png']);
        $attachment = $context->run($tenant, fn () => ExamAttachment::query()->where('exam_type_id', $exam->id)->first());
        if ($attachment !== null) {
            $this->remember($tenant, 'exam_type_attachments', (int) $attachment->id);
            $this->files[(int) $tenant->id][] = ['exam_id' => (int) $exam->id, 'path' => $attachment->path];
        }
        ($this->verify)($response->status() === 302 && $attachment !== null, $label.'PNG sintético enviado por multipart real');
        $download = $request('GET', $examPath.'/attachments/'.$attachment->id.'/download');
        ($this->verify)($download->ok() && hash_equals($png, $download->body()) && str_contains((string) $download->header('Content-Disposition'), 'attachment'), $label.'download autorizado devolve bytes exatos');
        $ciphertext = $context->run($tenant, fn () => app(TenantStorage::class)->get($attachment->path));
        ($this->verify)($ciphertext !== null && $ciphertext !== $png && Crypt::decryptString($ciphertext) === $png, $label.'arquivo privado cifrado em storage');
        ($this->verify)($request('GET', '/storage/tenants/'.$tenant->id.'/'.$attachment->path)->status() === 404, $label.'arquivo não possui URL pública');

        $response = $post('/questionnaires', ['code' => $code, 'name' => $name, 'ordem' => '0']);
        $group = $context->run($tenant, fn () => HealthQuestionGroup::query()->where('code', $code)->first());
        if ($group !== null) {
            $this->remember($tenant, 'health_question_groups', (int) $group->id);
        }
        ($this->verify)($response->status() === 302 && $group !== null, $label.'grupo de perguntas criado por HTTP');
        $response = $post('/questionnaires/'.$group->id.'/questions', ['code' => $code, 'label' => 'Enunciado sintético sem conteúdo médico.', 'ordem' => '0']);
        $question = $context->run($tenant, fn () => HealthQuestion::query()->where('group_id', $group->id)->first());
        if ($question !== null) {
            $this->remember($tenant, 'health_questions', (int) $question->id);
        }
        ($this->verify)($response->status() === 302 && $question !== null, $label.'primeira versão de pergunta por HTTP');
        $admin = $context->run($tenant, fn () => User::query()->where('email', 'admin.homologacao@example.test')->sole());
        $snapshot = $context->run($tenant, function () use ($admin, $group, $question) {
            auth()->setUser($admin);

            return app(BuildHealthQuestionSnapshot::class)->execute($group, $question);
        });
        $response = $post('/questionnaires/'.$group->id.'/questions/'.$question->id.'/versions', ['label' => 'Enunciado sintético revisado.']);
        $nextQuestion = $context->run($tenant, fn () => HealthQuestion::query()->where('group_id', $group->id)->orderByDesc('version')->first());
        if ($nextQuestion !== null && $nextQuestion->id !== $question->id) {
            $this->remember($tenant, 'health_questions', (int) $nextQuestion->id);
        }
        ($this->verify)($response->status() === 302 && $nextQuestion?->version === 2 && $snapshot->label === 'Enunciado sintético sem conteúdo médico.' && $context->run($tenant, fn () => $question->fresh()->label === $snapshot->label), $label.'nova versão preserva definição e snapshot anteriores');
        ($this->verify)($post('/questionnaires/'.$group->id.'/questions/'.$question->id.'/versions', ['label' => 'Versão obsoleta'])->status() === 422, $label.'origem obsoleta recusada');
        ($this->verify)($request('GET', '/questionnaires/'.$group->id.'/questions/'.$nextQuestion->id)->ok(), $label.'versão revisada consultável por HTTP');

        $email = strtolower($run).'-'.$clinic.'@example.test';
        $response = $post('/patients', ['name' => $name, 'email' => $email]);
        $patient = $context->run($tenant, fn () => Patient::query()->where('email', $email)->first());
        if ($patient !== null) {
            $this->remember($tenant, 'patients', (int) $patient->id);
        }
        ($this->verify)($response->status() === 302 && $patient !== null, $label.'paciente sintético para manifestações');
        $consentInput = ['purpose' => 'Finalidade sintética', 'legal_basis' => 'Base sintética para teste', 'policy_version' => 'synthetic-v1', 'policy_text' => 'Termo sintético sem efeito jurídico.', 'evidence_note' => 'Manifestação fictícia conferida em teste.', 'decision' => 'granted', 'attested' => '1'];
        $consentPath = '/patients/'.$patient->public_id.'/consents';
        foreach (['granted', 'withdrawn'] as $decision) {
            $response = $post($consentPath, ['decision' => $decision] + $consentInput);
            $consent = $context->run($tenant, fn () => PatientConsent::query()->where('patient_id', $patient->id)->where('decision', $decision)->first());
            if ($consent !== null) {
                $this->remember($tenant, 'patient_consents', (int) $consent->id);
            }
            ($this->verify)($response->status() === 302 && $consent !== null, $label.'manifestação '.$decision.' registrada por HTTP');
        }
        $consent = $context->run($tenant, fn () => PatientConsent::query()->where('patient_id', $patient->id)->orderBy('id')->firstOrFail());
        $rawEvidence = $consent->getRawOriginal('evidence_encrypted');
        ($this->verify)(! str_contains($rawEvidence, $consentInput['policy_text']) && hash('sha256', $consentInput['policy_text']) === $consent->policy_text_hash && $context->run($tenant, fn () => PatientConsent::query()->where('patient_id', $patient->id)->count()) === 2, $label.'retirada preserva evento anterior e evidência cifrada');
        ($this->verify)(str_contains($request('GET', $consentPath.'/'.$consent->id)->body(), $consentInput['evidence_note']), $label.'evidência consultável com autorização');

        $password = 'Aa1!'.Str::random(32);
        $secret = new Google2FA()->generateSecretKey();
        $approver = $context->run($tenant, function () use ($run, $clinic, $password, $secret): User {
            $user = User::query()->create(['name' => 'Aprovador sintético etapa 3', 'email' => strtolower($run).'-approver-'.$clinic.'@example.test', 'password' => $password]);
            $user->forceFill(['must_change_password' => false, 'two_factor_secret' => Crypt::encrypt($secret), 'two_factor_confirmed_at' => now()])->save();

            return $user;
        });
        $this->remember($tenant, 'users', (int) $approver->id);
        ($this->verify)($context->run($tenant, fn () => $approver->fresh()->permissions->isEmpty() && $approver->fresh()->roles->isEmpty()), $label.'novo profissional começa sem permissões');
        $response = $post('/professionals', ['user_id' => $approver->id, 'council' => 'CRM', 'council_uf' => 'MG', 'council_number' => 'SYN-'.substr($run, -10)]);
        $professional = $context->run($tenant, fn () => Professional::query()->where('user_id', $approver->id)->first());
        if ($professional !== null) {
            $this->remember($tenant, 'professionals', (int) $professional->id);
        }
        ($this->verify)($response->status() === 302 && $professional !== null && $context->run($tenant, fn () => ! $approver->fresh()->can('protocols.approve')), $label.'cadastro profissional não concede aprovação');
        $response = $post('/companies', ['name' => $name, 'is_active' => '1']);
        $company = $context->run($tenant, fn () => Company::query()->where('name', $name)->first());
        if ($company !== null) {
            $this->remember($tenant, 'companies', (int) $company->id);
        }
        ($this->verify)($response->status() === 302 && $company !== null, $label.'empresa sintética para programa');
        $programPath = '/companies/'.$company->public_id.'/programs';
        $response = $post($programPath.'/register/'.$professional->id, ['version' => $run, 'valid_from' => '2026-01-01', 'valid_until' => null, 'source_reference' => 'Fonte sintética para teste', 'context' => 'Contexto fictício sem conteúdo clínico.']);
        $program = $context->run($tenant, fn () => OccupationalProgram::query()->where('company_id', $company->id)->first());
        if ($program !== null) {
            $this->remember($tenant, 'occupational_programs', (int) $program->id);
        }
        ($this->verify)($response->status() === 302 && $program !== null && ! str_contains($program->getRawOriginal('context_encrypted'), 'Contexto fictício'), $label.'referência de programa com contexto cifrado');
        $protocolPath = '/programs/'.$program->id.'/protocols';
        $protocolInput = ['code' => $code, 'valid_from' => '2026-11-04', 'valid_until' => null, 'source_reference' => 'Fonte fictícia', 'job_title' => 'Função fictícia', 'risk_context' => 'Contexto apenas sintético, não aplicável a pacientes.', 'exam_codes' => $code];
        $response = $post($protocolPath, $protocolInput);
        $protocol = $context->run($tenant, fn () => OccupationalProtocol::query()->where('program_id', $program->id)->first());
        if ($protocol !== null) {
            $this->remember($tenant, 'occupational_protocols', (int) $protocol->id);
        }
        ($this->verify)($response->status() === 302 && $protocol !== null && $protocol->approved_by === null && $protocol->approved_at === null, $label.'protocolo criado pendente sem aprovação automática');
        ($this->verify)($post($protocolPath.'/'.$protocol->id.'/approve', ['attested' => '1'])->status() === 403, $label.'administrador sem concessão não aprova');
        $context->run($tenant, fn () => $approver->givePermissionTo(['companies.view', 'programs.view', 'protocols.view', 'protocols.approve', 'clinical.access', 'catalog.view']));
        $approverCookies = $this->loginApprover($host, $approver, $password, $secret, $label);
        $approverCsrf = ($this->token)(($this->request)($host, $approverCookies, 'GET', '/'));
        $approve = fn (array $data) => ($this->request)($host, $approverCookies, 'POST', $protocolPath.'/'.$protocol->id.'/approve', ['_token' => $approverCsrf] + $data);
        ($this->verify)($approve(['attested' => '1'])->status() === 423, $label.'aprovação exige senha recente');
        ($this->verify)(($this->request)($host, $approverCookies, 'POST', '/user/confirm-password', ['_token' => $approverCsrf, 'password' => $password])->successful(), $label.'aprovador confirma senha real');
        ($this->verify)(($this->request)($host, $approverCookies, 'GET', $protocolPath.'/'.$protocol->id)->ok(), $label.'aprovador consulta configuração');
        ($this->verify)($approve(['attested' => '1'])->status() === 302 && $context->run($tenant, fn () => (int) $protocol->fresh()->approved_by === (int) $professional->id), $label.'aprovação vinculada ao próprio profissional');
        ($this->verify)($approve(['attested' => '1'])->status() === 422, $label.'aprovação não pode ser reescrita');
        $revisionInput = $protocolInput;
        unset($revisionInput['code']);
        $response = $post($protocolPath.'/'.$protocol->id.'/versions', $revisionInput);
        $nextProtocol = $context->run($tenant, fn () => OccupationalProtocol::query()->where('program_id', $program->id)->orderByDesc('version')->first());
        if ($nextProtocol !== null && $nextProtocol->id !== $protocol->id) {
            $this->remember($tenant, 'occupational_protocols', (int) $nextProtocol->id);
        }
        ($this->verify)($response->status() === 302 && $nextProtocol?->version === 2 && $nextProtocol->approved_at === null && $context->run($tenant, fn () => $protocol->fresh()->approved_at !== null), $label.'nova versão não herda aprovação nem altera a anterior');
        $context->run($tenant, fn () => $approver->revokePermissionTo('protocols.approve'));
        ($this->verify)(($this->request)($host, $approverCookies, 'POST', $protocolPath.'/'.$nextProtocol->id.'/approve', ['_token' => $approverCsrf, 'attested' => '1'])->status() === 403, $label.'permissão retirada impede nova aprovação');
        ($this->verify)($context->run($tenant, fn () => $approver->fresh()->roles->isEmpty() && ! $admin->fresh()->can('protocols.approve')), $label.'fixtures não alteram papéis nem concedem aprovação ao administrador');
        ($this->verify)(($this->request)($host, $approverCookies, 'POST', '/logout', ['_token' => $approverCsrf])->successful(), $label.'aprovador sintético encerra sessão');

        return compact('tenant', 'host', 'cookies', 'csrf', 'exam', 'attachment', 'group', 'question', 'patient', 'consent', 'company', 'program', 'protocol');
    }

    private function loginApprover(string $host, User $user, string $password, string $secret, string $label): CookieJar
    {
        $cookies = new CookieJar;
        $csrf = ($this->token)(($this->request)($host, $cookies, 'GET', '/login'));
        $response = ($this->request)($host, $cookies, 'POST', '/login', ['_token' => $csrf, 'email' => $user->email, 'password' => $password]);
        ($this->verify)($response->successful() && $response->json('two_factor') === true, $label.'aprovador exige TOTP real');
        $response = ($this->request)($host, $cookies, 'POST', '/two-factor-challenge', ['_token' => $csrf, 'code' => new Google2FA()->getCurrentOtp($secret)]);
        ($this->verify)($response->successful(), $label.'aprovador autentica com TOTP real');

        return $cookies;
    }

    private function crossCheck(array $a, array $b): void
    {
        foreach ([[$a, $b, 'A'], [$b, $a, 'B']] as [$own, $other, $clinic]) {
            $request = fn (string $path) => ($this->request)($own['host'], $own['cookies'], 'GET', $path);
            $label = 'Etapa 3 '.$clinic.': ';
            ($this->verify)($request('/exam-types/'.$other['exam']->public_id)->status() === 404, $label.'exame externo recusado');
            ($this->verify)($request('/exam-types/'.$own['exam']->public_id.'/attachments/'.$other['attachment']->id.'/download')->status() === 404, $label.'anexo externo recusado');
            ($this->verify)($request('/questionnaires/'.$own['group']->id.'/questions/'.$other['question']->id)->status() === 404, $label.'pergunta externa recusada');
            ($this->verify)($request('/patients/'.$own['patient']->public_id.'/consents/'.$other['consent']->id)->status() === 404, $label.'manifestação externa recusada');
            ($this->verify)($request('/companies/'.$own['company']->public_id.'/programs/'.$other['program']->id)->status() === 404, $label.'programa externo recusado');
            ($this->verify)($request('/programs/'.$own['program']->id.'/protocols/'.$other['protocol']->id)->status() === 404, $label.'protocolo externo recusado');
        }
    }

    private function cleanup(): void
    {
        app(LocalHomologation::class)->ensure();
        $context = app(TenantContext::class);
        foreach ($this->files as $tenantId => $files) {
            $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();
            $context->run($tenant, function () use ($files): void {
                foreach ($files as $file) {
                    if (! preg_match('~\Acatalog/exams/'.$file['exam_id'].'/attachments/[0-9A-HJKMNP-TV-Z]{26}\.png\z~D', $file['path'])) {
                        throw new RuntimeException('Unexpected synthetic attachment path.');
                    }
                    if (! app(ExamAttachmentStorage::class)->delete($file['path']) || Storage::disk('local')->exists(app(TenantStorage::class)->path($file['path']))) {
                        throw new RuntimeException('Synthetic attachment cleanup incomplete.');
                    }
                }
            });
        }
        DB::transaction(function (): void {
            foreach ($this->fixtures as $tenantId => $fixture) {
                $users = $fixture['users'] ?? [];
                foreach (['model_has_permissions', 'model_has_roles'] as $table) {
                    DB::table($table)->where('tenant_id', $tenantId)->where('model_type', User::class)->whereIn('model_id', $users)->delete();
                }
                DB::table('sessions')->where('tenant_id', $tenantId)->whereIn('user_id', $users)->delete();
                foreach (['occupational_protocols', 'occupational_programs', 'patient_consents', 'patients', 'health_questions', 'health_question_groups', 'exam_type_attachments', 'exam_type_preparations', 'exam_types', 'companies', 'professionals', 'users'] as $table) {
                    $ids = $fixture[$table] ?? [];
                    DB::table($table)->where('tenant_id', $tenantId)->whereIn('id', $ids)->delete();
                    if (DB::table($table)->where('tenant_id', $tenantId)->whereIn('id', $ids)->exists()) {
                        throw new RuntimeException('Stage three synthetic cleanup incomplete.');
                    }
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ($this->verify)(true, 'Etapa 3: fixtures, arquivos, sessões e concessões temporárias removidos; auditoria preservada');
    }
}
