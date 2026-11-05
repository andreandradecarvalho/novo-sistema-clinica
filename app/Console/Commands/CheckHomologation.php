<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantContext;
use App\Support\LocalHomologation;
use App\Support\OrderHomologation;
use App\Support\RegistryHomologation;
use App\Support\StageThreeHomologation;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\Support\ContextProbeJob;
use Throwable;

final class CheckHomologation extends Command
{
    #[\Override]
    protected $signature = 'homologation:check {--registries : Include temporary registry/identity fixtures through HTTP} {--stage3 : Include registries and stage three flows through HTTP} {--orders : Include stage three and temporary order flows through HTTP}';

    #[\Override]
    protected $description = 'Validate the isolated local homologation through real HTTP and Horizon.';

    private array $checks = [];

    public function handle(): int
    {
        try {
            app(LocalHomologation::class)->ensure();
        } catch (RuntimeException) {
            $this->error('This command requires the isolated local homologation database.');

            return self::FAILURE;
        }
        try {
            $this->verify(config('app.debug') === false, 'Debug desativado');
            $this->verify(config('session.driver') === 'tenant-database' && config('session.encrypt') === true, 'Sessões persistidas e criptografadas');
            $this->verify(config('queue.default') === 'redis' && config('cache.default') === 'redis', 'Fila e cache Redis');
            $this->validateHttp();
            $this->validateWorker();
        } catch (Throwable) {
            // Never serialize exceptions, request bodies, headers or credentials into evidence.
            if ($this->checks === [] || end($this->checks)['passed']) {
                $this->checks[] = ['name' => 'Erro na execução; consultar o ambiente local', 'passed' => false];
            }
        }
        $passed = ! in_array(false, array_column($this->checks, 'passed'), true);
        $report = [
            'observed_at' => now()->toIso8601String(), 'environment' => 'homologacao-local-isolada',
            'php' => PHP_VERSION, 'laravel' => app()->version(),
            'database' => DB::selectOne('SELECT VERSION() AS version')->version,
            'foreign_key_checks' => (int) DB::selectOne('SELECT @@foreign_key_checks AS enabled')->enabled,
            'passed' => $passed, 'registries_included' => (bool) ($this->option('registries') || $this->option('stage3') || $this->option('orders')), 'stage3_included' => (bool) ($this->option('stage3') || $this->option('orders')), 'orders_included' => (bool) $this->option('orders'), 'checks' => $this->checks,
            'limits' => ['HTTP local; HTTPS externo não homologado', 'Somente dados sintéticos', 'Não substitui CI remota ou revisão de PR'],
        ];
        $reportPath = $this->option('orders') ? 'app/homologacao-orders-report.json' : ($this->option('stage3') ? 'app/homologacao-stage3-report.json' : ($this->option('registries') ? 'app/homologacao-registries-report.json' : 'app/homologacao-report.json'));
        file_put_contents(storage_path($reportPath), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
        $this->info(($passed ? 'PASS' : 'FAIL').' — '.count($this->checks).' verificações. Relatório sem segredos em storage/'.$reportPath.'.');

        return $passed ? self::SUCCESS : self::FAILURE;
    }

    private function verify(bool $condition, string $name): void
    {
        $this->checks[] = ['name' => $name, 'passed' => $condition];
        $this->line(($condition ? 'PASS ' : 'FAIL ').$name);
        if (! $condition) {
            throw new RuntimeException('Homologation assertion failed.');
        }
    }

    private function request(string $host, CookieJar $cookies, string $method, string $path, array $data = [], array $headers = [], ?array $file = null): Response
    {
        $request = Http::baseUrl('http://app:8000')->timeout(15)->withOptions(['cookies' => $cookies, 'allow_redirects' => false])
            ->withHeaders(['Host' => $host, 'Accept' => $method === 'GET' ? 'text/html' : 'application/json'] + $headers);

        if ($file !== null) {
            return $request->attach($file['field'], $file['contents'], $file['name'])->post($path, $data);
        }

        return $method === 'GET' ? $request->get($path) : $request->asForm()->post($path, $data);
    }

    private function token(Response $response): string
    {
        if (! preg_match('/name="_token"\s+value="([^"]+)"/', $response->body(), $matches)) {
            throw new RuntimeException('No CSRF token in synthetic page.');
        }

        return html_entity_decode($matches[1], ENT_QUOTES);
    }

    private function adminLogin(string $clinic, Tenant $tenant): CookieJar
    {
        $host = 'clinica-'.strtolower($clinic).'.localhost';
        $cookies = new CookieJar;
        $page = $this->request($host, $cookies, 'GET', '/login');
        $this->verify($page->ok() && str_contains($page->body(), 'authentication-cover'), 'Login Vuexy da clínica '.$clinic);
        $token = $this->token($page);
        $other = $clinic === 'A' ? 'B' : 'A';
        $wrong = $this->request($host, $cookies, 'POST', '/login', ['_token' => $token, 'email' => 'admin.homologacao@example.test', 'password' => config('homologation.clinics.'.$other.'.admin_password')]);
        $this->verify($wrong->status() === 422, 'Mesmo e-mail rejeita senha da outra clínica '.$clinic);
        $login = $this->request($host, $cookies, 'POST', '/login', ['_token' => $token, 'email' => 'admin.homologacao@example.test', 'password' => config('homologation.clinics.'.$clinic.'.admin_password')]);
        $this->verify($login->successful() && $login->json('two_factor') === true, 'Senha exige desafio 2FA '.$clinic);
        $this->verify($this->request($host, $cookies, 'GET', '/')->status() === 302, 'Senha sem 2FA não abre dashboard '.$clinic);
        $secret = (string) config('homologation.clinics.'.$clinic.'.totp_secret');
        $deadline = time() + 35;
        do {
            $code = (new Google2FA)->getCurrentOtp($secret);
            $used = app(TenantContext::class)->run($tenant, fn () => Cache::has(app(TenantContext::class)->key('2fa.used:'.hash('sha256', $secret.'|'.$code))));
            if (! $used) {
                break;
            }
            usleep(250000);
        } while (time() < $deadline);
        $challenge = $this->request($host, $cookies, 'POST', '/two-factor-challenge', ['_token' => $token, 'code' => $code]);
        $this->verify($challenge->successful(), 'TOTP real autenticou clínica '.$clinic);
        $this->verify($this->request($host, $cookies, 'GET', '/')->ok(), 'Sessão SQL abre dashboard '.$clinic);

        return $cookies;
    }

    private function validateHttp(): void
    {
        $a = Tenant::query()->where('slug', 'homologacao-a')->sole();
        $b = Tenant::query()->where('slug', 'homologacao-b')->sole();
        $context = app(TenantContext::class);
        $adminB = $context->run($b, fn () => User::query()->where('email', 'admin.homologacao@example.test')->sole());
        $cookiesA = $this->adminLogin('A', $a);
        $cookiesB = $this->adminLogin('B', $b);
        if ($this->option('registries') || $this->option('stage3') || $this->option('orders')) {
            new RegistryHomologation($this->request(...), $this->verify(...), $this->token(...))->run(['A' => [$a, $cookiesA], 'B' => [$b, $cookiesB]]);
        }
        if ($this->option('stage3') || $this->option('orders')) {
            new StageThreeHomologation($this->request(...), $this->verify(...), $this->token(...))->run(['A' => [$a, $cookiesA], 'B' => [$b, $cookiesB]]);
        }
        if ($this->option('orders')) {
            new OrderHomologation($this->request(...), $this->verify(...), $this->token(...))->run(['A' => [$a, $cookiesA], 'B' => [$b, $cookiesB]]);
        }
        $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', '/users')->ok(), 'Administrador consulta equipe própria');
        $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', '/users/'.$adminB->public_id)->status() === 404, 'Binding não expõe integrante da outra clínica');
        $replay = new CookieJar(false, $cookiesA->toArray());
        $this->verify($this->request('clinica-b.localhost', $replay, 'GET', '/')->status() === 302, 'Cookie A reapresentado em B não autentica');
        $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', '/')->ok(), 'Reapresentação não destrói sessão A');
        $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', '/', [], ['X-Tenant' => $b->public_id])->status() === 404, 'Header não seleciona outra clínica');
        $this->verify($this->request('unknown.localhost', new CookieJar, 'GET', '/login')->status() === 404, 'Domínio desconhecido rejeitado');
        foreach (['/.env', '/docs/README.md', '/vendor/autoload.php'] as $path) {
            $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', $path)->status() === 404, 'Arquivo privado indisponível: '.$path);
        }
        $this->verify($this->request('clinica-a.localhost', $cookiesA, 'GET', '/horizon')->status() === 403, 'Horizon não expõe dados entre clínicas');
        $this->verify($this->request('clinica-a.localhost', new CookieJar, 'POST', '/login', ['email' => 'admin.homologacao@example.test', 'password' => 'invalid'])->status() === 419, 'CSRF real rejeita POST sem token');
        foreach (['A', 'B'] as $clinic) {
            $host = 'clinica-'.strtolower($clinic).'.localhost';
            $cookies = new CookieJar;
            $token = $this->token($this->request($host, $cookies, 'GET', '/login'));
            $this->verify($this->request($host, $cookies, 'POST', '/login', ['_token' => $token, 'email' => 'recepcao.homologacao@example.test', 'password' => config('homologation.clinics.'.$clinic.'.reception_password')])->successful(), 'Recepção autenticou clínica '.$clinic);
            if ($clinic === 'A') {
                $dashboard = $this->request($host, $cookies, 'GET', '/');
                $this->verify($dashboard->status() === 302 && str_ends_with((string) $dashboard->header('Location'), '/security'), 'Troca obrigatória da senha inicial preservada');
            } else {
                $this->verify($this->request($host, $cookies, 'GET', '/users')->status() === 403, 'Recepção sem permissão não consulta equipe');
            }
        }
        foreach ([['clinica-a.localhost', $cookiesA], ['clinica-b.localhost', $cookiesB]] as [$host, $cookies]) {
            $token = $this->token($this->request($host, $cookies, 'GET', '/'));
            $this->verify($this->request($host, $cookies, 'POST', '/logout', ['_token' => $token])->successful(), 'Logout no HTTP real '.$host);
        }
    }

    private function validateWorker(): void
    {
        $a = Tenant::query()->where('slug', 'homologacao-a')->sole();
        $b = Tenant::query()->where('slug', 'homologacao-b')->sole();
        $context = app(TenantContext::class);
        $userA = $context->run($a, fn () => User::query()->where('email', 'admin.homologacao@example.test')->sole());
        $userB = $context->run($b, fn () => User::query()->where('email', 'recepcao.homologacao@example.test')->sole());
        $key = 'homologation:probe:'.Str::ulid();
        try {
            foreach ([[$a, $userA, false], [$a, $userA, true], [$b, $userB, false]] as [$tenant, $user, $fail]) {
                $context->run($tenant, fn () => Queue::connection('redis')->push(new ContextProbeJob($key, (int) $tenant->id, (int) $user->id, $fail)));
            }
            $deadline = time() + 35;
            do {
                $values = Redis::lrange($key, 0, -1);
                if (count($values) === 3) {
                    break;
                }
                usleep(250000);
            } while (time() < $deadline);
            $rows = array_map(fn (string $value) => json_decode($value, true, flags: JSON_THROW_ON_ERROR), $values);
            $this->verify(count($rows) === 3, 'Horizon processou três jobs reais');
            $this->verify(count(array_unique(array_column($rows, 'pid'))) === 1, 'Mesmo worker persistente em A/falha/B');
            $this->verify(array_column($rows, 'tenant_id') === [$a->id, $a->id, $b->id] && array_column($rows, 'team_id') === [$a->id, $a->id, $b->id], 'Contexto e team corretos após falha');
            $this->verify(array_column($rows, 'can_view') === [true, true, false], 'Permissões não vazam no Horizon');
        } finally {
            Redis::del($key);
        }
    }
}
