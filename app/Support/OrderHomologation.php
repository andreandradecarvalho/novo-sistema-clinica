<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Companies\Models\Company;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Services\ProfessionalExamAccess;
use App\Domain\Identity\Models\User;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Patients\Models\Patient;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;
use Closure;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class OrderHomologation
{
    private const array TABLES = ['exam_types', 'patients', 'exam_orders', 'users', 'companies', 'appointments', 'units', 'rooms', 'professionals', 'health_questions', 'health_question_groups'];

    private array $fixtures = [];

    public function __construct(private readonly Closure $request, private readonly Closure $verify, private readonly Closure $token) {}

    public function run(array $clinics): void
    {
        app(LocalHomologation::class)->ensure();
        $run = strtolower((string) Str::ulid());
        $results = [];
        try {
            foreach ($clinics as $clinic => [$tenant, $cookies]) {
                $results[$clinic] = $this->exercise($tenant, $clinic, $cookies, $run);
            }
            foreach ([[$results['A'], $results['B'], 'A'], [$results['B'], $results['A'], 'B']] as [$own, $other, $clinic]) {
                $path = '/patients/'.$own['patient']->public_id.'/orders/'.$other['order']->public_id;
                $request = fn (string $method, string $url, array $data = []) => ($this->request)($own['host'], $own['cookies'], $method, $url, ['_token' => $own['csrf']] + $data);
                ($this->verify)($request('GET', $path.'/receivables')->status() === 404 && $request('POST', $path.'/receivables', ['due_date' => '2026-12-10'])->status() === 404, 'Recebíveis '.$clinic.': pedido externo recusado na consulta e criação');
                ($this->verify)($request('GET', $path)->status() === 404, 'Pedidos '.$clinic.': consulta de pedido externo recusada');
                ($this->verify)($request('POST', $path.'/authorize', ['expected_status' => 'draft'])->status() === 404, 'Pedidos '.$clinic.': autorização externa recusada');
                ($this->verify)($request('POST', $path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->status() === 404, 'Pedidos '.$clinic.': cancelamento externo recusado');
                $financialPath = '/patients/'.$own['patient']->public_id.'/orders/'.$own['financialOrder']->public_id.'/receivables/'.$other['financialReceipt']->id;
                ($this->verify)($request('POST', $financialPath.'/settle', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 404
                    && $request('POST', $financialPath.'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 404, 'Recebíveis '.$clinic.': transições de recebível externo recusadas');
                $appointmentPath = '/patients/'.$own['patient']->public_id.'/appointments/'.$other['booking']['appointment']->public_id;
                ($this->verify)($request('GET', $appointmentPath)->status() === 404 && $request('POST', $appointmentPath.'/cancel', ['expected_status' => 'pre_agendado', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 404, 'Reservas '.$clinic.': consulta/cancelamento externos recusados');
                ($this->verify)($request('POST', '/patients/'.$own['patient']->public_id.'/appointments', ['unit_id' => $other['booking']['unit']->id, 'scheduled_date' => '2026-12-12', 'idempotency_key' => (string) Str::uuid()])->status() === 422, 'Reservas '.$clinic.': unidade externa recusada');
                $foreignAppointment = '/patients/'.$own['patient']->public_id.'/appointments/'.$other['booking']['appointment']->public_id;
                ($this->verify)($request('POST', $foreignAppointment.'/confirm', ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()])->status() === 404
                    && $request('POST', $foreignAppointment.'/check-in', $own['visitCase']['input'])->status() === 404, 'Atendimento '.$clinic.': confirmação/check-in de reserva externa recusados');
                ($this->verify)($request('GET', '/patients/'.$own['patient']->public_id.'/visits/'.$other['visitCase']['visit']->public_id)->status() === 404, 'Atendimento '.$clinic.': consulta de atendimento externo recusada');
                ($this->verify)($request('POST', $own['visitCase']['path'].'/check-in', ['exam_order_id' => $other['visitCase']['order']->id] + $own['visitCase']['input'])->status() === 422, 'Atendimento '.$clinic.': pedido externo recusado no check-in');
                $foreignExecution = $own['executionCase']['base'].'/'.$other['executionCase']['attempt']->id.'/cancel';
                ($this->verify)($request('POST', $foreignExecution, $own['executionCase']['cancel'])->status() === 404, 'Tentativas '.$clinic.': cancelamento externo recusado');
                ($this->verify)($request('POST', $own['executionCase']['base'], ['exam_order_item_id' => $other['executionCase']['input']['exam_order_item_id']] + $own['executionCase']['input'])->status() === 422, 'Tentativas '.$clinic.': item externo recusado');
                ($this->verify)($request('POST', $own['executionCase']['base'].'/'.$other['executionCase']['attempt']->id.'/room', $own['executionCase']['roomAssignment']['input'])->status() === 404, 'Tentativas '.$clinic.': atribuição de sala externa recusada');
                $foreignRoomPath = $own['roomCase']['base'].'/'.$other['roomCase']['room']->id;
                ($this->verify)($request('GET', $foreignRoomPath)->status() === 404 && $request('POST', $foreignRoomPath, ['_method' => 'PUT'] + $own['roomCase']['input'])->status() === 404, 'Salas '.$clinic.': consulta e alteração externas recusadas');
                $response = $request('POST', '/patients/'.$own['patient']->public_id.'/orders', ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => [$other['exam']->id]]);
                ($this->verify)($response->status() === 422, 'Pedidos '.$clinic.': exame externo recusado na criação');
                $foreignCompany = '/patients/'.$own['patient']->public_id.'/orders/company/'.$other['company']->public_id;
                ($this->verify)($request('GET', $foreignCompany.'/create')->status() === 404, 'Pedidos '.$clinic.': seleção de pagadora externa recusada');
                ($this->verify)($request('POST', $foreignCompany, ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => [$own['exam']->id]])->status() === 404, 'Pedidos '.$clinic.': criação com pagadora externa recusada');
            }
        } finally {
            $this->cleanup();
        }
    }

    private function remember(Tenant $tenant, string $table, int $id): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new RuntimeException('Unsupported order fixture table.');
        }
        $this->fixtures[(int) $tenant->id][$table][] = $id;
    }

    private function exercise(Tenant $tenant, string $clinic, CookieJar $cookies, string $run): array
    {
        $context = app(TenantContext::class);
        $host = 'clinica-'.strtolower($clinic).'.localhost';
        $request = fn (string $method, string $path, array $data = []) => ($this->request)($host, $cookies, $method, $path, $data);
        $csrf = ($this->token)($request('GET', '/'));
        $post = fn (string $path, array $data) => $request('POST', $path, ['_token' => $csrf] + $data);
        $label = 'Pedidos '.$clinic.': ';
        $name = 'Pedido sintético '.$run.' '.$clinic;
        $exams = [];
        foreach (['12.34', '0.00'] as $index => $price) {
            $input = ['code' => 'O-'.substr($run, -12).$clinic.$index, 'name' => $name.' '.$index, 'price' => $price, 'requires_fasting' => '0', 'ordem' => '0', 'is_active' => '1'];
            $response = $post('/exam-types', $input);
            $exam = $context->run($tenant, fn () => ExamType::query()->where('code', $input['code'])->first());
            if ($exam !== null) {
                $this->remember($tenant, 'exam_types', (int) $exam->id);
            }
            ($this->verify)($response->status() === 302 && $exam !== null, $label.'exame sintético '.$index.' criado por HTTP');
            $exams[] = $exam;
        }
        $patients = [];
        foreach (['root', 'other'] as $kind) {
            $email = $run.'-'.$clinic.'-'.$kind.'@example.test';
            $response = $post('/patients', ['name' => $name, 'email' => $email]);
            $patient = $context->run($tenant, fn () => Patient::query()->where('email', $email)->first());
            if ($patient !== null) {
                $this->remember($tenant, 'patients', (int) $patient->id);
            }
            ($this->verify)($response->status() === 302 && $patient !== null, $label.'paciente '.$kind.' criado por HTTP');
            $patients[$kind] = $patient;
        }
        $patient = $patients['root'];
        $exam = $exams[0];
        $base = '/patients/'.$patient->public_id.'/orders';
        ($this->verify)($request('GET', $base.'/create')->ok(), $label.'formulário de pedido acessível');
        $order = $this->createOrder($tenant, $patient, $exams, $post, $label);
        $financialOrder = $this->createOrder($tenant, $patient, $exams, $post, $label);
        $paidOrder = $this->createOrder($tenant, $patient, $exams, $post, $label);
        $path = $base.'/'.$order->public_id;
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains((string) $page->header('Cache-Control'), 'no-store'), $label.'consulta privada de rascunho por HTTP');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft', 'unit_price' => '0.01'])->status() === 422, $label.'injeção de preço na autorização recusada');
        $input = ['_method' => 'PUT', 'code' => $exam->code, 'name' => $name.' revisado', 'price' => '99.99', 'requires_fasting' => '0', 'ordem' => '0', 'is_active' => '1'];
        ($this->verify)($post('/exam-types/'.$exam->public_id, $input)->status() === 302, $label.'catálogo alterado depois do rascunho');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), '12.34') && ! str_contains($page->body(), '99.99') && ! str_contains($page->body(), $name.' revisado'), $label.'consulta mantém preço e descrição históricos');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 302 && $context->run($tenant, fn () => $order->fresh()->status === 'authorized' && $order->fresh()->authorized_at !== null && $order->items()->where('status', '<>', 'authorized')->doesntExist()), $label.'pedido e itens autorizados juntos por HTTP');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 422, $label.'autorização repetida recusada');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->status() === 422, $label.'estado obsoleto no cancelamento recusado');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => ''])->status() === 422, $label.'cancelamento sem motivo recusado');
        $reason = 'Motivo operacional sintético <script>synthetic()</script>';
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => $reason])->status() === 302, $label.'cancelamento autorizado registrado por HTTP');
        $context->run($tenant, function () use ($order, $reason, $label): void {
            $fresh = $order->fresh();
            $history = $fresh->contextSnapshot();
            $admin = User::query()->where('email', 'admin.homologacao@example.test')->sole();
            ($this->verify)($fresh->status === 'canceled' && $fresh->authorized_at !== null && $history['authorization']['actor_id'] === (int) $admin->id && $history['cancellation']['actor_id'] === (int) $admin->id && $history['cancellation']['reason'] === $reason && ! str_contains($fresh->getRawOriginal('context_encrypted'), $reason), $label.'cifra e autoria preservam autorização e cancelamento');
            ($this->verify)($fresh->items()->where('status', '<>', 'canceled')->doesntExist() && $fresh->items()->orderBy('id')->pluck('unit_price')->all() === ['12.34', '0.00'], $label.'cancelamento preserva preços e altera todos os itens');
        });
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'motivo exibido com escaping');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Repetido'])->status() === 422, $label.'cancelamento repetido recusado');
        ($this->verify)($request('POST', $path.'/authorize', ['expected_status' => 'draft'])->status() === 419, $label.'CSRF real rejeita transição sem token');
        $otherPath = '/patients/'.$patients['other']->public_id.'/orders/'.$order->public_id;
        ($this->verify)($request('GET', $otherPath)->status() === 404 && $post($otherPath.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->status() === 404, $label.'pedido não atravessa paciente da mesma clínica');
        $financialPath = $this->exerciseReceivable($tenant, $patient, $financialOrder, $paidOrder, $request, $post, $label);
        $financialReceipt = $context->run($tenant, fn () => $financialOrder->receivables()->sole());
        [$company, $companyPath] = $this->exerciseCompany($tenant, $patient, $exams[1], $name, $request, $post, $label);
        $booking = $this->exerciseAppointments($tenant, $patient, $name, $request, $post, $label);
        $roomCase = $this->exerciseRooms($tenant, $booking, $run, $request, $post, $label);
        $visitCase = $this->exerciseVisit($tenant, $patient, $booking, $exams[1], $request, $post, $label);
        $executionCase = $this->exercisePendingExecutions($tenant, $visitCase, $roomCase, $request, $post, $label);
        $draft = $this->createOrder($tenant, $patient, [$exams[1]], $post, $label);
        $draftPath = $base.'/'.$draft->public_id;
        $this->checkViewer($tenant, $clinic, $host, $run, $base, $draftPath, (int) $exams[1]->id, $label, $companyPath, $financialPath, $booking, $visitCase, $roomCase, $executionCase);
        $this->exerciseVisitCancellation($tenant, $visitCase, $request, $post, $label);
        ($this->verify)($post($draftPath.'/cancel', ['expected_status' => 'draft', 'reason' => 'Cancelamento sintético de rascunho'])->status() === 302 && $context->run($tenant, fn () => $draft->fresh()->status === 'canceled' && $draft->fresh()->authorized_at === null), $label.'rascunho cancelado sem autorização');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ExamOrder::class)->whereIn('subject_id', [$order->id, $draft->id])->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'exam_order_authorized')->count() === 1 && $events->where('event', 'exam_order_canceled')->count() === 2 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria de transições sem motivo ou preços');

        return compact('tenant', 'host', 'cookies', 'csrf', 'patient', 'order', 'exam', 'company', 'financialOrder', 'financialReceipt', 'booking', 'visitCase', 'roomCase', 'executionCase');
    }

    private function createOrder(Tenant $tenant, Patient $patient, array $exams, Closure $post, string $label): ExamOrder
    {
        $input = ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => array_map(fn (ExamType $exam): int => (int) $exam->id, $exams)];
        $response = $post('/patients/'.$patient->public_id.'/orders', $input);
        $order = app(TenantContext::class)->run($tenant, fn () => $patient->orders()->orderByDesc('id')->first());
        if ($order !== null) {
            $this->remember($tenant, 'exam_orders', (int) $order->id);
        }
        ($this->verify)($response->status() === 302 && $order !== null && $order->status === 'draft', $label.'novo rascunho criado por HTTP');
        $replay = $post('/patients/'.$patient->public_id.'/orders', ['exam_ids' => array_reverse($input['exam_ids'])] + $input);
        ($this->verify)($replay->status() === 302 && str_ends_with((string) $replay->header('Location'), '/'.$order->public_id), $label.'reenvio equivalente recupera o mesmo pedido');
        $conflict = $post('/patients/'.$patient->public_id.'/orders', ['exam_ids' => [...$input['exam_ids'], 999999999]] + $input);
        ($this->verify)($conflict->status() === 422 && $conflict->json('errors.idempotency_key') !== null, $label.'chave reutilizada com outros exames recusada');

        return $order;
    }

    private function exerciseReceivable(Tenant $tenant, Patient $patient, ExamOrder $order, ExamOrder $paidOrder, Closure $request, Closure $post, string $label): string
    {
        $context = app(TenantContext::class);
        $path = '/patients/'.$patient->public_id.'/orders/'.$order->public_id;
        $url = $path.'/receivables';
        $input = ['due_date' => '2026-12-10'];
        ($this->verify)($post($url, $input)->status() === 422, $label.'rascunho não gera recebível');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 302, $label.'pedido financeiro autorizado por HTTP');
        ($this->verify)($request('GET', $url)->ok(), $label.'formulário financeiro acessível');
        ($this->verify)($request('POST', $url, $input)->status() === 419, $label.'CSRF real protege recebível');
        ($this->verify)($post($url, ['amount' => '0.01'] + $input)->status() === 422, $label.'valor financeiro injetado recusado');
        ($this->verify)($post($url, ['due_date' => '2026-02-30'])->status() === 422, $label.'vencimento inválido recusado');
        $response = $post($url, $input);
        $receivable = $context->run($tenant, fn () => $order->receivables()->sole());
        ($this->verify)($response->status() === 302 && $receivable->amount === '12.34' && $receivable->status === 'open' && $receivable->settled_at === null && $receivable->external_ref === null, $label.'recebível integral usa valor histórico sem cobrança externa');
        ($this->verify)($post($url, $input)->status() === 302 && $context->run($tenant, fn () => $order->receivables()->sole()->id === $receivable->id), $label.'reenvio financeiro recupera registro único');
        ($this->verify)($post($url, ['due_date' => '2026-12-11'])->status() === 422, $label.'reenvio financeiro divergente recusado');
        $page = $request('GET', $url);
        ($this->verify)($page->ok() && str_contains($page->body(), '12.34') && str_contains($page->body(), '10/12/2026') && str_contains((string) $page->header('Cache-Control'), 'no-store'), $label.'consulta financeira privada preserva valor e vencimento');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->status() === 422
            && $context->run($tenant, fn () => $order->fresh()->status === 'authorized' && $order->items()->where('status', '<>', 'authorized')->doesntExist()), $label.'recebível impede cancelamento silencioso do pedido');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ClinicalReceivable::class)->where('subject_id', $receivable->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'clinical_receivable_created')->count() === 1 && $events->where('event', 'clinical_receivable_replayed')->count() === 1 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria financeira transacional sem conteúdo');
        $transitionPath = $url.'/'.$receivable->id;
        $change = ['expected_status' => 'open', 'reason' => 'Cancelamento financeiro sintético <script>synthetic()</script>', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($request('POST', $transitionPath.'/cancel', $change)->status() === 419, $label.'CSRF real protege cancelamento financeiro');
        ($this->verify)($post($transitionPath.'/cancel', ['reason' => ''] + $change)->status() === 422, $label.'cancelamento financeiro exige motivo');
        ($this->verify)($post($transitionPath.'/cancel', $change)->status() === 302 && $context->run($tenant, fn () => $receivable->fresh()->status === 'canceled'), $label.'cancelamento financeiro registrado por HTTP');
        ($this->verify)($post($transitionPath.'/cancel', $change)->status() === 302, $label.'reenvio de cancelamento financeiro recupera transição');
        ($this->verify)($post($transitionPath.'/cancel', ['reason' => 'Outro motivo'] + $change)->status() === 422, $label.'cancelamento financeiro divergente recusado');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético após cancelamento financeiro'])->status() === 302, $label.'cancelamento financeiro permite cancelar pedido');
        $page = $request('GET', $url);
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'motivo financeiro preservado e escapado');
        $paidPath = '/patients/'.$patient->public_id.'/orders/'.$paidOrder->public_id;
        ($this->verify)($post($paidPath.'/authorize', ['expected_status' => 'draft'])->status() === 302 && $post($paidPath.'/receivables', $input)->status() === 302, $label.'recebível de baixa preparado por HTTP');
        $paid = $context->run($tenant, fn () => $paidOrder->receivables()->sole());
        $paymentPath = $paidPath.'/receivables/'.$paid->id;
        $payment = ['reason' => 'Baixa interna sintética', 'idempotency_key' => (string) Str::uuid(), 'expected_status' => 'open'];
        ($this->verify)($post($paymentPath.'/settle', ['amount' => '0.01'] + $payment)->status() === 422, $label.'baixa recusa valor injetado');
        ($this->verify)($post($paymentPath.'/settle', $payment)->status() === 302, $label.'baixa interna registrada por HTTP');
        ($this->verify)($post($paymentPath.'/settle', $payment)->status() === 302, $label.'reenvio de baixa recupera transição');
        ($this->verify)($post($paymentPath.'/cancel', $payment)->status() === 422 && $post($paidPath.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->status() === 422, $label.'recebível baixado impede cancelamento financeiro e do pedido');
        $context->run($tenant, function () use ($paid, $receivable, $label): void {
            $event = $paid->fresh()->transitionSnapshot();
            ($this->verify)($paid->fresh()->status === 'paid' && $paid->fresh()->settled_at !== null && $paid->fresh()->amount === '12.34' && $event['reason'] === 'Baixa interna sintética'
                && ! str_contains((string) $paid->fresh()->getRawOriginal('transition_encrypted'), $event['reason']) && $receivable->fresh()->transitionSnapshot()['operation'] === 'cancel', $label.'evidência financeira cifrada com data e valor preservados');
        });
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ClinicalReceivable::class)->whereIn('subject_id', [$paid->id, $receivable->id])->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'clinical_receivable_paid')->count() === 1 && $events->where('event', 'clinical_receivable_canceled')->count() === 1
            && $events->where('event', 'clinical_receivable_transition_replayed')->count() === 2 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria registra transições únicas e recuperações sem conteúdo');

        return $transitionPath;
    }

    private function exerciseAppointments(Tenant $tenant, Patient $patient, string $name, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $units = [];
        foreach ([1, 2] as $number) {
            $unitName = 'Unidade reserva '.$name.' '.$number;
            $response = $post('/units', ['name' => $unitName, 'is_active' => '1']);
            $unit = $context->run($tenant, fn () => Unit::query()->where('name', $unitName)->first());
            if ($unit !== null) {
                $this->remember($tenant, 'units', (int) $unit->id);
            }
            ($this->verify)($response->status() === 302 && $unit !== null, $label.'unidade de reserva '.$number.' criada por HTTP');
            $units[] = $unit;
        }
        $base = '/patients/'.$patient->public_id.'/appointments';
        ($this->verify)($request('GET', $base.'/create')->ok(), $label.'formulário de reserva diária acessível');
        $input = ['unit_id' => $units[0]->id, 'scheduled_date' => '2026-12-10', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($request('POST', $base, $input)->status() === 419, $label.'CSRF real protege reserva');
        ($this->verify)($post($base, ['scheduled_at' => '2026-12-10 10:00:00'] + $input)->status() === 422, $label.'reserva diária recusa horário injetado');
        $response = $post($base, $input);
        $appointment = $context->run($tenant, fn () => $patient->appointments()->where('unit_id', $units[0]->id)->sole());
        $this->remember($tenant, 'appointments', (int) $appointment->id);
        $path = $base.'/'.$appointment->public_id;
        ($this->verify)($response->status() === 302 && $appointment->status === 'pre_agendado' && $appointment->scheduled_at === null, $label.'reserva diária criada por HTTP');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains((string) $page->header('Cache-Control'), 'no-store') && str_contains($page->body(), '10/12/2026'), $label.'consulta privada da reserva');
        ($this->verify)($post($base, $input)->status() === 302 && $context->run($tenant, fn () => $patient->appointments()->count() === 1), $label.'reenvio da reserva não duplica registro');
        ($this->verify)($post($base, ['scheduled_date' => '2026-12-11'] + $input)->status() === 422, $label.'chave da reserva com outra data recusada');
        ($this->verify)($post($base, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'reserva duplicada do paciente no mesmo dia e unidade recusada');
        $cancel = ['expected_status' => 'pre_agendado', 'reason' => 'Reserva sintética <script>synthetic()</script>', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($post($path.'/cancel', ['reason' => ''] + $cancel)->status() === 422, $label.'cancelamento de reserva exige motivo');
        ($this->verify)($post($path.'/cancel', $cancel)->status() === 302 && $context->run($tenant, fn () => $appointment->fresh()->active_flag === null), $label.'cancelamento preserva reserva e libera slot');
        ($this->verify)($post($path.'/cancel', $cancel)->status() === 302, $label.'reenvio de cancelamento de reserva é idempotente');
        ($this->verify)($post($path.'/cancel', ['reason' => 'Outro motivo'] + $cancel)->status() === 422, $label.'cancelamento de reserva divergente recusado');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'motivo da reserva é escapado');
        $new = $post($base, ['idempotency_key' => (string) Str::uuid()] + $input);
        $replacement = $context->run($tenant, fn () => $patient->appointments()->where('unit_id', $units[0]->id)->where('status', 'pre_agendado')->sole());
        $this->remember($tenant, 'appointments', (int) $replacement->id);
        ($this->verify)($new->status() === 302 && $replacement->id !== $appointment->id, $label.'nova intenção ocupa slot liberado sem apagar histórico');
        $second = $post($base, ['unit_id' => $units[1]->id, 'idempotency_key' => (string) Str::uuid()] + $input);
        $otherAppointment = $context->run($tenant, fn () => $patient->appointments()->where('unit_id', $units[1]->id)->sole());
        $this->remember($tenant, 'appointments', (int) $otherAppointment->id);
        ($this->verify)($second->status() === 302, $label.'permissão explícita permite reservar em outra unidade');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', Appointment::class)->where('subject_id', $appointment->id)->get(['event', 'properties']);
        ($this->verify)($context->run($tenant, fn () => $appointment->fresh()->contextSnapshot()['cancellation']['reason'] === $cancel['reason'] && ! str_contains($appointment->fresh()->getRawOriginal('context_encrypted'), $cancel['reason']))
            && $events->where('event', 'appointment_canceled')->count() === 1 && $events->where('event', 'appointment_cancellation_replayed')->count() === 1 && $events->pluck('properties')->filter()->isEmpty(), $label.'reserva mantém evidência cifrada e auditoria sem conteúdo');

        return ['base' => $base, 'unit' => $units[0], 'other_unit' => $units[1], 'appointment' => $replacement, 'other_appointment' => $otherAppointment];
    }

    private function exerciseRooms(Tenant $tenant, array $booking, string $run, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $base = '/units/'.$booking['unit']->public_id.'/rooms';
        $otherBase = '/units/'.$booking['other_unit']->public_id.'/rooms';
        $input = ['room_key' => 'r-'.substr($run, -10), 'name' => 'Sala sintética <script>synthetic()</script>', 'ordem' => 1, 'is_active' => '1'];
        ($this->verify)($request('GET', $base.'/create')->ok(), $label.'formulário de sala disponível');
        ($this->verify)($request('POST', $base, $input)->status() === 419, $label.'CSRF real protege cadastro de sala');
        ($this->verify)($post($base, ['unit_id' => $booking['other_unit']->id] + $input)->status() === 422
            && $post($base, ['pin_hash' => 'synthetic'] + $input)->status() === 422, $label.'sala recusa unidade e PIN injetados');
        $response = $post($base, $input);
        $room = $context->run($tenant, fn () => Room::query()->where('room_key', strtoupper($input['room_key']))->first());
        if ($room !== null) {
            $this->remember($tenant, 'rooms', (int) $room->id);
        }
        ($this->verify)($response->status() === 302 && $room !== null, $label.'sala cadastrada por HTTP');
        $path = $base.'/'.$room->id;
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains((string) $page->header('Cache-Control'), 'no-store')
            && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'consulta de sala privada e escapada');
        ($this->verify)($post($otherBase, $input)->status() === 422, $label.'chave de sala é única entre unidades da clínica');
        ($this->verify)($request('GET', $path.'/edit')->ok()
            && $post($path, ['_method' => 'PUT', 'is_active' => '0'] + $input)->status() === 302, $label.'sala editada e inativada por HTTP');
        ($this->verify)($post($otherBase, $input)->status() === 422, $label.'sala inativa mantém sua chave reservada');
        ($this->verify)($post($path, ['_method' => 'PUT'] + $input)->status() === 302
            && $context->run($tenant, fn () => $room->fresh()->is_active && $room->fresh()->unit_id === $booking['unit']->id && $room->fresh()->pin_hash === null), $label.'reativação preserva unidade sem criar credencial');
        $otherInput = ['room_key' => 'O-'.substr($run, -10), 'name' => 'Sala de outra unidade'] + $input;
        $response = $post($otherBase, $otherInput);
        $otherRoom = $context->run($tenant, fn () => Room::query()->where('room_key', $otherInput['room_key'])->first());
        if ($otherRoom !== null) {
            $this->remember($tenant, 'rooms', (int) $otherRoom->id);
        }
        ($this->verify)($response->status() === 302 && $otherRoom !== null, $label.'sala de segunda unidade cadastrada');
        ($this->verify)($request('GET', $base.'/'.$otherRoom->id)->status() === 404 && $post($base.'/'.$otherRoom->id, ['_method' => 'PUT'] + $input)->status() === 404, $label.'sala não atravessa unidade no binding');
        $page = $request('GET', $base);
        ($this->verify)($page->ok() && ! str_contains($page->body(), 'Sala de outra unidade'), $label.'lista de salas limitada à unidade');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', Room::class)->where('subject_id', $room->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'room_created')->count() === 1 && $events->where('event', 'room_updated')->count() === 2 && $events->pluck('properties')->filter()->isEmpty(), $label.'salas auditadas sem conteúdo ou PIN');

        return compact('base', 'otherBase', 'room', 'otherRoom', 'input');
    }

    private function exerciseVisit(Tenant $tenant, Patient $patient, array $booking, ExamType $exam, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $order = $this->createOrder($tenant, $patient, [$exam], $post, $label);
        $orderPath = '/patients/'.$patient->public_id.'/orders/'.$order->public_id;
        ($this->verify)($post($orderPath.'/authorize', ['expected_status' => 'draft'])->status() === 302 && $post($orderPath.'/receivables', ['due_date' => '2026-12-10'])->status() === 302, $label.'pedido/recebível do atendimento preparados por HTTP');
        $receipt = $context->run($tenant, fn () => $order->receivables()->sole());
        $appointment = $booking['appointment'];
        $path = $booking['base'].'/'.$appointment->public_id;
        $confirmation = ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()];
        $input = ['expected_status' => 'confirmado', 'idempotency_key' => (string) Str::uuid(), 'exam_order_id' => $order->id];
        ($this->verify)($post($path.'/check-in', $input)->status() === 422, $label.'check-in exige confirmação anterior');
        ($this->verify)($request('POST', $path.'/confirm', $confirmation)->status() === 419, $label.'CSRF real protege confirmação');
        ($this->verify)($post($path.'/confirm', ['confirmed_at' => '2026-12-10 10:00:00'] + $confirmation)->status() === 422, $label.'data de confirmação injetada recusada');
        ($this->verify)($post($path.'/confirm', $confirmation)->status() === 302, $label.'confirmação manual registrada por HTTP');
        ($this->verify)($post($path.'/confirm', $confirmation)->status() === 302, $label.'reenvio de confirmação é idempotente');
        ($this->verify)($post($path.'/confirm', ['idempotency_key' => (string) Str::uuid()] + $confirmation)->status() === 422, $label.'confirmação com nova intenção recusada');
        ($this->verify)($request('GET', $path.'/check-in')->ok(), $label.'seleção de pedido para check-in acessível');
        ($this->verify)($request('POST', $path.'/check-in', $input)->status() === 419, $label.'CSRF real protege check-in');
        ($this->verify)($post($path.'/check-in', ['unit_id' => $booking['other_unit']->id] + $input)->status() === 422, $label.'unidade injetada no check-in recusada');
        $response = $post($path.'/check-in', $input);
        $visit = $context->run($tenant, fn () => Visit::query()->where('appointment_id', $appointment->id)->sole());
        ($this->verify)($response->status() === 302 && $visit->status === 'open', $label.'check-in abre atendimento único por HTTP');
        ($this->verify)($post($path.'/check-in', $input)->status() === 302 && $context->run($tenant, fn () => Visit::query()->where('appointment_id', $appointment->id)->count() === 1), $label.'reenvio recupera o mesmo atendimento');
        ($this->verify)($post($path.'/check-in', ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'segunda intenção de check-in recusada');
        $visitPath = '/patients/'.$patient->public_id.'/visits/'.$visit->public_id;
        $page = $request('GET', $visitPath);
        ($this->verify)($page->ok() && str_contains((string) $page->header('Cache-Control'), 'no-store') && str_contains($page->body(), 'Atendimento aberto'), $label.'consulta privada do atendimento');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'confirmado', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 422, $label.'reserva com check-in não pode ser cancelada');
        ($this->verify)($post($orderPath.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->status() === 422, $label.'pedido com atendimento não pode ser cancelado');
        ($this->verify)($post($orderPath.'/receivables/'.$receipt->id.'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 422, $label.'recebível com atendimento exige revisão financeira');
        $context->run($tenant, function () use ($visit, $order, $appointment, $label): void {
            $history = $visit->fresh()->attendanceSnapshot();
            $admin = User::query()->where('email', 'admin.homologacao@example.test')->sole();
            ($this->verify)($history['actor_id'] === (int) $admin->id && $visit->patient_id === $order->patient_id && $visit->unit_id === $appointment->unit_id
                && $appointment->fresh()->status === 'checked_in' && $appointment->fresh()->confirmed_at !== null && $order->fresh()->status === 'authorized'
                && $order->items()->sole()->status === 'authorized' && ! str_contains($visit->getRawOriginal('context_encrypted'), 'key_hash'), $label.'atendimento preserva autoria/cifra/vínculos sem iniciar exame');
        });
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', Visit::class)->where('subject_id', $visit->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'visit_opened')->count() === 1 && $events->where('event', 'visit_check_in_replayed')->count() === 1 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria única do atendimento sem conteúdo');

        return compact('visit', 'order', 'receipt', 'visitPath', 'path', 'input');
    }

    private function exercisePendingExecutions(Tenant $tenant, array $case, array $roomCase, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $base = $case['visitPath'].'/executions';
        $itemId = $context->run($tenant, fn () => $case['order']->items()->sole()->id);
        $input = ['expected_status' => 'open', 'exam_order_item_id' => $itemId, 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($request('GET', $base)->ok(), $label.'lista e preparação de tentativas disponíveis');
        ($this->verify)($request('POST', $base, $input)->status() === 419, $label.'CSRF real protege preparação da tentativa');
        ($this->verify)($post($base, ['performed_by' => 1] + $input)->status() === 422
            && $post($base, ['room_id' => 1] + $input)->status() === 422, $label.'preparação não aceita executor ou sala injetados');
        $response = $post($base, $input);
        $attempt = $context->run($tenant, fn () => ExamExecution::query()->where('visit_id', $case['visit']->id)->sole());
        ($this->verify)($response->status() === 302 && $attempt->status === 'pending' && $attempt->attempt_no === 1, $label.'tentativa pendente preparada por HTTP');
        ($this->verify)($post($base, $input)->status() === 302
            && $context->run($tenant, fn () => $case['visit']->executions()->count() === 1), $label.'reenvio recupera preparação sem duplicação');
        ($this->verify)($post($base, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'segunda intenção não duplica item pendente');
        ($this->verify)($post($case['visitPath'].'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 422, $label.'tentativa pendente impede cancelar atendimento');
        $context->run($tenant, function () use ($case, $attempt, $label): void {
            $history = $attempt->preparationSnapshot();
            ($this->verify)($history['actor_id'] === (int) $attempt->created_by && $attempt->room_id === null && $attempt->performed_by === null && $attempt->started_at === null && $attempt->finished_at === null
                && $case['visit']->fresh()->status === 'open' && $case['order']->fresh()->status === 'authorized' && $case['order']->items()->sole()->status === 'authorized'
                && $case['receipt']->fresh()->status === 'open', $label.'preparação não inicia exame nem altera chegada pedido ou financeiro');
        });
        $roomAssignment = $this->exerciseExecutionRoom($tenant, $case, $attempt, $roomCase, $request, $post, $label);
        $cancel = ['expected_status' => 'pending', 'reason' => 'Motivo sintético <script>synthetic()</script>', 'idempotency_key' => (string) Str::uuid()];
        $path = $base.'/'.$attempt->id.'/cancel';
        ($this->verify)($request('POST', $path, $cancel)->status() === 419, $label.'CSRF real protege cancelamento de tentativa');
        ($this->verify)($post($path, $cancel)->status() === 302 && $post($path, ['idempotency_key' => strtoupper($cancel['idempotency_key'])] + $cancel)->status() === 302, $label.'cancelamento da pendência e reenvio aprovados');
        ($this->verify)($post($path, ['reason' => 'Outro motivo'] + $cancel)->status() === 422
            && $post($path, ['idempotency_key' => (string) Str::uuid()] + $cancel)->status() === 422, $label.'cancelamento recusa mudança de motivo ou intenção');
        $page = $request('GET', $base);
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'motivo de cancelamento escapado na consulta');
        ($this->verify)($post($base, $input)->status() === 302, $label.'reenvio da preparação recupera tentativa cancelada');
        ($this->verify)($post($base, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 302, $label.'nova intenção cria tentativa adicional');
        $next = $context->run($tenant, fn () => $case['visit']->executions()->where('attempt_no', 2)->sole());
        ($this->verify)($post($base.'/'.$next->id.'/cancel', ['idempotency_key' => (string) Str::uuid()] + $cancel)->status() === 302, $label.'segunda tentativa cancelada sem excluir a anterior');
        $context->run($tenant, function () use ($case, $attempt, $cancel, $label): void {
            $fresh = $attempt->fresh();
            ($this->verify)($fresh->preparationSnapshot()['cancellation']['reason'] === $cancel['reason'] && ! str_contains($fresh->getRawOriginal('reason_encrypted'), $cancel['reason'])
                && $case['visit']->executions()->orderBy('attempt_no')->pluck('status')->all() === ['canceled', 'canceled'], $label.'tentativas e motivo cifrado preservados');
        });
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ExamExecution::class)->where('subject_id', $attempt->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'execution_prepared')->count() === 1 && $events->where('event', 'execution_preparation_replayed')->count() === 2
            && $events->where('event', 'execution_canceled')->count() === 1 && $events->where('event', 'execution_cancellation_replayed')->count() === 1
            && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria das tentativas sem motivo ou chave');

        return compact('base', 'attempt', 'input', 'cancel', 'roomAssignment');
    }

    private function exerciseExecutionRoom(Tenant $tenant, array $case, ExamExecution $attempt, array $rooms, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $path = $case['visitPath'].'/executions/'.$attempt->id.'/room';
        $input = ['expected_status' => 'pending', 'room_id' => $rooms['room']->id, 'idempotency_key' => (string) Str::uuid()];
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && ! str_contains($page->body(), 'Sala de outra unidade'), $label.'seleção de sala limita unidade do atendimento');
        ($this->verify)($request('POST', $path, $input)->status() === 419, $label.'CSRF real protege atribuição de sala');
        ($this->verify)($post($path, ['performed_by' => 1] + $input)->status() === 422, $label.'atribuição de sala não recebe executor injetado');
        ($this->verify)($post($path, ['room_id' => $rooms['otherRoom']->id] + $input)->status() === 422, $label.'sala de outra unidade recusada na tentativa');
        ($this->verify)($post($path, $input)->status() === 302, $label.'sala atribuída à pendência por HTTP');
        ($this->verify)($post($path, ['idempotency_key' => strtoupper($input['idempotency_key'])] + $input)->status() === 302, $label.'reenvio recupera atribuição da mesma sala');
        ($this->verify)($post($path, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'nova intenção não sobrescreve atribuição');
        $context->run($tenant, function () use ($attempt, $case, $rooms, $label): void {
            $fresh = $attempt->fresh();
            $history = $fresh->preparationSnapshot()['room_assignment'];
            ($this->verify)($fresh->status === 'pending' && $fresh->room_id === $rooms['room']->id && $fresh->performed_by === null && $fresh->started_at === null && $fresh->finished_at === null
                && $history['room_name'] === $rooms['room']->name && $case['visit']->fresh()->status === 'open' && $case['order']->fresh()->status === 'authorized'
                && ! str_contains($fresh->getRawOriginal('context_encrypted'), $rooms['room']->name), $label.'atribuição cifra histórico sem iniciar execução ou pedido');
        });
        ($this->verify)($post($rooms['base'].'/'.$rooms['room']->id, ['_method' => 'PUT', 'name' => 'Nome atual de sala atribuída', 'is_active' => '0'] + $rooms['input'])->status() === 302, $label.'cadastro da sala alterado e inativado após atribuição');
        $page = $request('GET', $case['visitPath'].'/executions');
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>')
            && ! str_contains($page->body(), 'Nome atual de sala atribuída'), $label.'consulta preserva nome histórico escapado da sala');
        ($this->verify)($post($path, $input)->status() === 302, $label.'reenvio histórico não exige reativar sala');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ExamExecution::class)->where('subject_id', $attempt->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'execution_room_assigned')->count() === 1 && $events->where('event', 'execution_room_assignment_replayed')->count() === 2
            && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria única de atribuição sem contexto ou chave bruta');

        return compact('path', 'input');
    }

    private function exerciseVisitCancellation(Tenant $tenant, array $case, Closure $request, Closure $post, string $label): void
    {
        $context = app(TenantContext::class);
        $path = $case['visitPath'];
        $input = ['expected_status' => 'open', 'reason' => 'Cancelamento sintético <script>synthetic()</script>', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($request('POST', $path.'/cancel', $input)->status() === 419, $label.'CSRF real protege cancelamento do atendimento');
        ($this->verify)($post($path.'/cancel', ['status' => 'canceled'] + $input)->status() === 422, $label.'estado injetado no atendimento recusado');
        ($this->verify)($post($path.'/cancel', $input)->status() === 302, $label.'atendimento aberto cancelado por HTTP');
        $first = $context->run($tenant, fn () => $case['visit']->fresh()->getAttributes());
        ($this->verify)($post($path.'/cancel', $input)->status() === 302 && $context->run($tenant, fn () => $case['visit']->fresh()->getAttributes() === $first), $label.'reenvio recupera cancelamento sem nova alteração');
        ($this->verify)($post($path.'/cancel', ['reason' => 'Outro motivo'] + $input)->status() === 422
            && $post($path.'/cancel', ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'cancelamento recusa mudança de motivo ou intenção');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), 'Atendimento cancelado') && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'cancelamento consultado com motivo escapado');
        ($this->verify)($post($case['path'].'/check-in', $case['input'])->status() === 302, $label.'reenvio do check-in preserva atendimento cancelado');
        $context->run($tenant, function () use ($case, $input, $label): void {
            $visit = $case['visit']->fresh();
            $evidence = $visit->attendanceSnapshot()['cancellation'];
            $admin = User::query()->where('email', 'admin.homologacao@example.test')->sole();
            ($this->verify)($visit->status === 'canceled' && $visit->completed_at === null && $evidence['actor_id'] === (int) $admin->id
                && $evidence['reason'] === $input['reason'] && ! str_contains($visit->getRawOriginal('context_encrypted'), $input['reason'])
                && $visit->appointment->status === 'checked_in' && $case['order']->fresh()->status === 'authorized'
                && $case['order']->items()->sole()->status === 'authorized' && $case['receipt']->fresh()->status === 'open', $label.'cancelamento cifrado preserva chegada pedido itens e recebível');
        });
        $orderPath = '/patients/'.$context->run($tenant, fn () => $case['visit']->patient->public_id).'/orders/'.$case['order']->public_id;
        ($this->verify)($post($case['path'].'/cancel', ['expected_status' => 'confirmado'] + $input)->status() === 422
            && $post($orderPath.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Sintético'])->status() === 422
            && $post($orderPath.'/receivables/'.$case['receipt']->id.'/cancel', ['expected_status' => 'open'] + $input)->status() === 422, $label.'atendimento cancelado mantém bloqueios de revisão dos vínculos');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', Visit::class)->where('subject_id', $case['visit']->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'visit_canceled')->count() === 1 && $events->where('event', 'visit_cancellation_replayed')->count() === 1 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria única do cancelamento sem motivo ou chave bruta');
    }

    private function exerciseCompany(Tenant $tenant, Patient $patient, ExamType $exam, string $name, Closure $request, Closure $post, string $label): array
    {
        $context = app(TenantContext::class);
        $companyName = 'Pagadora '.$name;
        $response = $post('/companies', ['name' => $companyName, 'is_active' => '1']);
        $company = $context->run($tenant, fn () => Company::query()->where('name', $companyName)->first());
        if ($company !== null) {
            $this->remember($tenant, 'companies', (int) $company->id);
        }
        ($this->verify)($response->status() === 302 && $company !== null, $label.'pagadora sintética criada por HTTP');
        $base = '/patients/'.$patient->public_id.'/orders';
        $companyBase = $base.'/company/'.$company->public_id;
        ($this->verify)($request('GET', $base.'/payers')->ok() && $request('GET', $companyBase.'/create')->ok(), $label.'seleção e formulário empresariais acessíveis');
        $input = ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => [$exam->id]];
        $response = $post($companyBase, $input);
        $order = $context->run($tenant, fn () => $patient->orders()->where('company_id', $company->id)->first());
        if ($order !== null) {
            $this->remember($tenant, 'exam_orders', (int) $order->id);
        }
        ($this->verify)($response->status() === 302 && $order !== null && $order->payer_type === 'company', $label.'pedido empresarial criado por HTTP');
        $replay = $post($companyBase, $input);
        ($this->verify)($replay->status() === 302 && str_ends_with((string) $replay->header('Location'), '/'.$order->public_id), $label.'reenvio empresarial recupera o mesmo pedido');
        $path = $base.'/'.$order->public_id;
        ($this->verify)($context->run($tenant, fn () => $order->contextSnapshot()['payer_name'] === $companyName && ! str_contains($order->getRawOriginal('context_encrypted'), $companyName) && $patient->fresh()->company_id === null)
            && ! DB::table('company_user_access')->where('tenant_id', $tenant->id)->where('company_id', $company->id)->exists(), $label.'pagadora cifrada sem vínculo ou concessão empresarial automática');
        $update = ['_method' => 'PUT', 'name' => $companyName.' alterada', 'is_active' => '0'];
        ($this->verify)($post('/companies/'.$company->public_id, $update)->status() === 302, $label.'cadastro atual da pagadora alterado e inativado');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), $companyName) && ! str_contains($page->body(), $companyName.' alterada'), $label.'consulta preserva identificação histórica da pagadora');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 422, $label.'pagadora inativa impede autorização');
        ($this->verify)($post('/companies/'.$company->public_id, ['is_active' => '1'] + $update)->status() === 302, $label.'pagadora reativada por HTTP');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 302, $label.'pedido empresarial autorizado por HTTP');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'authorized', 'reason' => 'Cancelamento empresarial sintético'])->status() === 302
            && $context->run($tenant, fn () => $order->fresh()->status === 'canceled' && $order->fresh()->contextSnapshot()['payer_name'] === $companyName), $label.'pedido empresarial cancelado preservando pagadora');

        return [$company, $path];
    }

    private function checkViewer(Tenant $tenant, string $clinic, string $host, string $run, string $base, string $path, int $examId, string $label, string $companyPath, string $financialPath, array $booking, array $visitCase, array $roomCase, array $executionCase): void
    {
        $context = app(TenantContext::class);
        $password = 'Aa1!'.Str::random(32);
        $secret = new Google2FA()->generateSecretKey();
        $user = $context->run($tenant, fn () => User::query()->create(['name' => 'Leitor sintético de pedidos', 'email' => $run.'-viewer-'.$clinic.'@example.test', 'password' => $password]));
        $this->remember($tenant, 'users', (int) $user->id);
        $context->run($tenant, function () use ($user, $secret): void {
            $user->forceFill(['must_change_password' => false, 'two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();
            $user->givePermissionTo(['patients.view', 'orders.view', 'catalog.view']);

        });
        $cookies = new CookieJar;
        $request = fn (string $method, string $url, array $data = []) => ($this->request)($host, $cookies, $method, $url, $data);
        $csrf = ($this->token)($request('GET', '/login'));
        $post = fn (string $url, array $data) => $request('POST', $url, ['_token' => $csrf] + $data);
        $login = $post('/login', ['email' => $user->email, 'password' => $password]);
        ($this->verify)($login->successful() && $login->json('two_factor') === true && $post('/two-factor-challenge', ['code' => new Google2FA()->getCurrentOtp($secret)])->successful(), $label.'leitor temporário autentica com senha e TOTP reais');
        $csrf = ($this->token)($request('GET', '/'));
        $post = fn (string $url, array $data) => $request('POST', $url, ['_token' => $csrf] + $data);
        ($this->verify)($request('GET', $path)->ok(), $label.'leitor autorizado consulta pedido');
        ($this->verify)($request('GET', $path.'/receivables')->status() === 403 && $post($path.'/receivables', ['due_date' => '2026-12-10'])->status() === 403, $label.'leitor de pedidos não consulta nem cria recebíveis');
        ($this->verify)($request('GET', $companyPath)->status() === 403, $label.'leitor sem consulta empresarial não vê pedido empresarial');
        ($this->verify)($post($base, ['idempotency_key' => (string) Str::uuid(), 'exam_ids' => [$examId]])->status() === 403, $label.'leitor sem gestão não cria pedido');
        ($this->verify)($post($path.'/authorize', ['expected_status' => 'draft'])->status() === 403, $label.'leitor sem gestão não autoriza');
        ($this->verify)($post($path.'/cancel', ['expected_status' => 'draft', 'reason' => 'Sintético'])->status() === 403, $label.'leitor sem gestão não cancela');
        ($this->verify)($post($financialPath.'/settle', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 403
            && $post($financialPath.'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 403, $label.'leitor não baixa nem cancela recebível');
        ($this->verify)($context->run($tenant, fn () => $user->fresh()->roles->isEmpty() && ! $user->fresh()->can('orders.manage')), $label.'leitor permanece sem papéis ou gestão');
        ($this->verify)($post($visitCase['path'].'/confirm', ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()])->status() === 403
            && $post($visitCase['path'].'/check-in', $visitCase['input'])->status() === 403 && $request('GET', $visitCase['visitPath'])->status() === 403, $label.'leitor sem permissões não confirma nem faz check-in ou lê atendimento');
        $bookingPath = $booking['base'].'/'.$booking['appointment']->public_id;
        $otherBookingPath = $booking['base'].'/'.$booking['other_appointment']->public_id;
        ($this->verify)($request('GET', $bookingPath)->status() === 403, $label.'leitor sem permissão de agenda não consulta reserva');
        $context->run($tenant, function () use ($user, $booking): void {
            $user->forceFill(['unit_id' => $booking['unit']->id])->save();
            $user->givePermissionTo(['units.view', 'appointments.view', 'appointments.create', 'appointments.cancel']);
        });
        ($this->verify)($request('GET', $bookingPath)->ok(), $label.'integrante de agenda consulta reserva da própria unidade');
        ($this->verify)($request('GET', $otherBookingPath)->status() === 403, $label.'integrante não consulta reserva de outra unidade');
        ($this->verify)($post($otherBookingPath.'/cancel', ['reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid(), 'expected_status' => 'pre_agendado'])->status() === 403, $label.'integrante não cancela reserva de outra unidade');
        ($this->verify)($post($booking['base'], ['unit_id' => $booking['other_unit']->id, 'scheduled_date' => '2026-12-11', 'idempotency_key' => (string) Str::uuid()])->status() === 403, $label.'integrante não cria reserva em outra unidade');
        $page = $request('GET', $booking['base']);
        ($this->verify)($page->ok() && ! str_contains($page->body(), $booking['other_appointment']->public_id), $label.'lista de reservas oculta outra unidade');
        $context->run($tenant, fn () => $user->givePermissionTo(['appointments.confirm', 'visits.view', 'visits.check_in']));
        ($this->verify)($request('GET', $visitCase['visitPath'])->ok(), $label.'consulta de atendimento autorizada na própria unidade');
        ($this->verify)($post($otherBookingPath.'/confirm', ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()])->status() === 403
            && $post($otherBookingPath.'/check-in', $visitCase['input'])->status() === 403, $label.'confirmação e check-in não atravessam unidade');
        ($this->verify)($post($visitCase['path'].'/check-in', $visitCase['input'])->status() === 422, $label.'UUID de outro ator não recupera check-in');
        ($this->verify)($post($visitCase['visitPath'].'/cancel', ['expected_status' => 'open', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 403, $label.'leitura de atendimento não autoriza seu cancelamento');
        ($this->verify)($request('GET', $roomCase['base'])->status() === 403 && $post($roomCase['base'], $roomCase['input'])->status() === 403, $label.'salas exigem permissões próprias');
        $context->run($tenant, fn () => $user->givePermissionTo('rooms.view'));
        ($this->verify)($request('GET', $roomCase['base'].'/'.$roomCase['room']->id)->ok() && $request('GET', $roomCase['otherBase'].'/'.$roomCase['otherRoom']->id)->ok(), $label.'consulta administrativa de salas abrange unidades autorizadas da clínica');
        ($this->verify)($post($roomCase['base'], $roomCase['input'])->status() === 403
            && $post($roomCase['base'].'/'.$roomCase['room']->id, ['_method' => 'PUT'] + $roomCase['input'])->status() === 403, $label.'consulta de salas não concede gestão');
        ($this->verify)($request('GET', $executionCase['base'])->status() === 403, $label.'tentativas exigem consulta própria');
        $context->run($tenant, fn () => $user->givePermissionTo('executions.view'));
        ($this->verify)($request('GET', $executionCase['base'])->ok() && $post($executionCase['base'], $executionCase['input'])->status() === 403
            && $post($executionCase['base'].'/'.$executionCase['attempt']->id.'/cancel', $executionCase['cancel'])->status() === 403, $label.'consulta de tentativas não concede preparação ou cancelamento');
        ($this->verify)($post($executionCase['roomAssignment']['path'], $executionCase['roomAssignment']['input'])->status() === 403, $label.'consulta de salas e tentativas não concede atribuição');
        $this->exerciseProfessionalGrants($tenant, $user, $examId, $password, $request, $post, $label);
        $this->exerciseExecutionStart($tenant, $user, $examId, $context->run($tenant, fn () => Patient::query()->findOrFail($visitCase['visit']->patient_id)), $booking, $request, $post, $label);
        ($this->verify)($post('/logout', [])->successful(), $label.'leitor temporário encerra sessão');
    }

    private function exerciseProfessionalGrants(Tenant $tenant, User $user, int $examId, string $password, Closure $request, Closure $post, string $label): void
    {
        $context = app(TenantContext::class);
        $context->run($tenant, fn () => $user->givePermissionTo(['professionals.view', 'professionals.manage']));
        $response = $post('/professionals', ['user_id' => $user->id]);
        $professional = $context->run($tenant, fn () => Professional::query()->where('user_id', $user->id)->first());
        if ($professional !== null) {
            $this->remember($tenant, 'professionals', (int) $professional->id);
        }
        ($this->verify)($response->status() === 302 && $professional !== null, $label.'perfil profissional sintético preparado sem validar conselho');
        $path = '/professionals/'.$professional->id.'/exam-authorizations';
        $input = ['exam_type_id' => $examId, 'reason' => 'Habilitação sintética <script>synthetic()</script>'];
        ($this->verify)($post('/user/confirm-password', ['password' => $password])->successful(), $label.'senha recente confirmada para concessões');
        ($this->verify)($request('GET', $path)->status() === 403 && $post($path, $input)->status() === 403, $label.'perfil e catálogo não concedem gestão de habilitações');
        $context->run($tenant, fn () => $user->givePermissionTo('executions.authorizations.view'));
        ($this->verify)($request('GET', $path)->ok() && $post($path, $input)->status() === 403, $label.'consulta de concessões não permite conceder');
        $context->run($tenant, fn () => $user->givePermissionTo('executions.authorizations.manage'));
        ($this->verify)($request('POST', $path, $input)->status() === 419, $label.'CSRF real protege concessão por profissional e exame');
        ($this->verify)($post($path, ['granted_by' => $user->id] + $input)->status() === 422, $label.'autoria da concessão não aceita injeção');
        ($this->verify)($post($path, $input)->status() === 302, $label.'concessão específica registrada por HTTP');
        ($this->verify)($post($path, $input)->status() === 422, $label.'concessão ativa duplicada recusada');
        $grant = $context->run($tenant, fn () => ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->sole());
        $context->run($tenant, function () use ($user, $examId, $grant, $label): void {
            $exam = ExamType::query()->findOrFail($examId);
            $access = app(ProfessionalExamAccess::class);
            ($this->verify)(! $access->allows($user, $exam), $label.'concessão isolada não concede permissão clínica ou execução');
            $user->givePermissionTo(['clinical.access', 'executions.perform']);
            ($this->verify)($access->allows($user, $exam) && ! str_contains($grant->getRawOriginal('grant_reason_encrypted'), 'Habilitação'), $label.'tipo autorizado exige concessão e direitos clínicos explícitos');
        });
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), '&lt;script&gt;synthetic()&lt;/script&gt;') && ! str_contains($page->body(), '<script>synthetic()</script>'), $label.'justificativa cifrada exibida com escaping');
        $revoke = ['reason' => 'Revogação sintética'];
        ($this->verify)($post($path.'/'.$grant->id.'/revoke', $revoke)->status() === 302, $label.'concessão revogada por HTTP');
        $context->run($tenant, function () use ($user, $examId, $label): void {
            ($this->verify)(! app(ProfessionalExamAccess::class)->allows($user, ExamType::query()->findOrFail($examId)), $label.'revogação bloqueia novo check sem cache');
        });
        ($this->verify)($post($path, $input)->status() === 302
            && $context->run($tenant, fn () => ProfessionalExamAuthorization::query()->where('professional_id', $professional->id)->count() === 2), $label.'nova concessão preserva registro revogado');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ProfessionalExamAuthorization::class)->where('subject_id', $grant->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'professional_exam_authorized')->count() === 1 && $events->where('event', 'professional_exam_authorization_revoked')->count() === 1
            && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria de concessão e revogação sem justificativas');
    }

    private function exerciseExecutionStart(Tenant $tenant, User $user, int $examId, Patient $patient, array $booking, Closure $request, Closure $post, string $label): void
    {
        $context = app(TenantContext::class);
        $context->run($tenant, fn () => $user->givePermissionTo(['orders.manage', 'executions.prepare', 'executions.assign_room', 'executions.cancel', 'rooms.manage', 'receivables.view', 'receivables.manage', 'receivables.settle']));
        $exam = $context->run($tenant, fn () => ExamType::query()->findOrFail($examId));
        $order = $this->createOrder($tenant, $patient, [$exam], $post, $label);
        $orderPath = '/patients/'.$patient->public_id.'/orders/'.$order->public_id;
        ($this->verify)($post($orderPath.'/authorize', ['expected_status' => 'draft'])->status() === 302, $label.'pedido separado para início autorizado por HTTP');
        $appointmentKey = (string) Str::uuid();
        $response = $post($booking['base'], ['unit_id' => $booking['unit']->id, 'scheduled_date' => '2026-12-12', 'idempotency_key' => $appointmentKey]);
        $appointment = $context->run($tenant, fn () => $patient->appointments()->orderByDesc('id')->firstOrFail());
        $this->remember($tenant, 'appointments', (int) $appointment->id);
        ($this->verify)($response->status() === 302, $label.'reserva separada para início criada por HTTP');
        $appointmentPath = $booking['base'].'/'.$appointment->public_id;
        ($this->verify)($post($appointmentPath.'/confirm', ['expected_status' => 'pre_agendado', 'idempotency_key' => (string) Str::uuid()])->status() === 302
            && $post($appointmentPath.'/check-in', ['expected_status' => 'confirmado', 'exam_order_id' => $order->id, 'idempotency_key' => (string) Str::uuid()])->status() === 302, $label.'atendimento separado confirmado e aberto por HTTP');
        $visit = $context->run($tenant, fn () => $patient->visits()->where('appointment_id', $appointment->id)->sole());
        $base = '/patients/'.$patient->public_id.'/visits/'.$visit->public_id.'/executions';
        $item = $context->run($tenant, fn () => $order->items()->sole());
        ($this->verify)($post($base, ['expected_status' => 'open', 'exam_order_item_id' => $item->id, 'idempotency_key' => (string) Str::uuid()])->status() === 302, $label.'tentativa separada preparada por HTTP');
        $attempt = $context->run($tenant, fn () => $visit->executions()->sole());
        $path = $base.'/'.$attempt->id.'/start';
        $input = ['expected_status' => 'pending', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($post($path, $input)->status() === 422, $label.'início exige sala previamente atribuída');
        $roomInput = ['room_key' => 'START-'.$user->id, 'name' => 'Sala sintética de início', 'ordem' => 0, 'is_active' => 1];
        $roomPath = '/units/'.$booking['unit']->public_id.'/rooms';
        $response = $post($roomPath, $roomInput);
        $room = $context->run($tenant, fn () => Room::query()->where('room_key', $roomInput['room_key'])->sole());
        $this->remember($tenant, 'rooms', (int) $room->id);
        ($this->verify)($response->status() === 302 && $post($base.'/'.$attempt->id.'/room', ['room_id' => $room->id, 'expected_status' => 'pending', 'idempotency_key' => (string) Str::uuid()])->status() === 302, $label.'sala própria atribuída para início por HTTP');
        ($this->verify)($request('POST', $path, $input)->status() === 419, $label.'CSRF real protege início do exame');
        ($this->verify)($post($path, ['performed_by' => 1] + $input)->status() === 422, $label.'executor não pode ser injetado no início');
        ($this->verify)($post($path, $input)->status() === 302, $label.'profissional assume e inicia com concessão específica por HTTP');
        $context->run($tenant, function () use ($attempt, $visit, $order, $item, $user, $label): void {
            $fresh = $attempt->fresh();
            $history = $fresh->preparationSnapshot();
            ($this->verify)($fresh->status === 'in_progress' && $visit->fresh()->status === 'in_progress' && $order->fresh()->status === 'in_progress' && $item->fresh()->status === 'in_progress'
                && $history['start']['actor_id'] === (int) $user->id && $fresh->started_at !== null && $fresh->finished_at === null, $label.'quatro estados e autoria do início coerentes com evidência cifrada');
        });
        $page = $request('GET', $base);
        ($this->verify)($page->ok() && str_contains($page->body(), 'Em execução') && ! str_contains($page->body(), 'Cancelar tentativa'), $label.'consulta privada mostra início e remove cancelamento administrativo');
        ($this->verify)($post($path, $input)->status() === 302 && $post($path, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'reenvio recupera início e outro UUID não repete execução');
        ($this->verify)($post($base.'/'.$attempt->id.'/cancel', ['expected_status' => 'pending', 'reason' => 'Sintético', 'idempotency_key' => (string) Str::uuid()])->status() === 422, $label.'tentativa iniciada não aceita cancelamento administrativo');
        ($this->verify)($post($orderPath.'/receivables', ['due_date' => '2026-12-12'])->status() === 302, $label.'cobrança integral continua disponível após início');
        $receipt = $context->run($tenant, fn () => $order->receivables()->sole());
        $this->exerciseHealthCollection($tenant, $user, $patient, $visit, $request, $post, $label);
        $this->exerciseExecutionCompletion($tenant, $user, $patient, $visit, $attempt, $request, $post, $label);
        ($this->verify)($post($orderPath.'/receivables/'.$receipt->id.'/settle', ['expected_status' => 'open', 'reason' => 'Baixa sintética após início', 'idempotency_key' => (string) Str::uuid()])->status() === 302, $label.'baixa financeira permanece independente após início');
    }

    private function exerciseHealthCollection(Tenant $tenant, User $user, Patient $patient, Visit $visit, Closure $request, Closure $post, string $label): void
    {
        $context = app(TenantContext::class);
        $context->run($tenant, fn () => $user->givePermissionTo('catalog.manage'));
        $code = 'COLLECT-'.$user->id;
        $response = $post('/questionnaires', ['code' => $code, 'name' => 'Grupo sintético de coleta', 'ordem' => 0]);
        $group = $context->run($tenant, fn () => HealthQuestionGroup::query()->where('code', $code)->sole());
        $this->remember($tenant, 'health_question_groups', (int) $group->id);
        ($this->verify)($response->status() === 302, $label.'grupo sintético de coleta criado por HTTP');
        $questionPath = '/questionnaires/'.$group->id.'/questions';
        $response = $post($questionPath, ['code' => $code, 'label' => 'Pergunta sintética <script>collect()</script>', 'ordem' => 0]);
        $question = $context->run($tenant, fn () => $group->questions()->sole());
        $this->remember($tenant, 'health_questions', (int) $question->id);
        ($this->verify)($response->status() === 302, $label.'versão explícita de pergunta criada por HTTP');
        $base = '/patients/'.$patient->public_id.'/visits/'.$visit->public_id.'/health-records';
        $input = ['question_id' => $question->id, 'answer' => 'Resposta sintética <script>collect()</script>', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($request('GET', $base)->status() === 403 && $post($base, $input)->status() === 403, $label.'execução clínica não concede consulta ou coleta de respostas');
        $context->run($tenant, fn () => $user->givePermissionTo('health_records.view'));
        ($this->verify)($request('GET', $base)->ok() && $post($base, $input)->status() === 403, $label.'consulta de respostas não concede coleta');
        $context->run($tenant, fn () => $user->givePermissionTo('health_records.create'));
        $page = $request('GET', $base.'/create?question_id='.$question->id);
        ($this->verify)($page->ok() && str_contains($page->body(), 'versão 1') && str_contains($page->body(), '&lt;script&gt;collect()&lt;/script&gt;'), $label.'formulário clínico exige versão escolhida e escapa pergunta');
        ($this->verify)($request('POST', $base, $input)->status() === 419, $label.'CSRF real protege resposta clínica');
        ($this->verify)($post($base, ['filled_by' => $user->id] + $input)->status() === 422, $label.'coleta não aceita autoria injetada');
        ($this->verify)($post($base, $input)->status() === 302, $label.'resposta clínica coletada por HTTP em atendimento em execução');
        $record = $context->run($tenant, fn () => $visit->healthRecords()->sole());
        $path = $base.'/'.$record->public_id;
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains((string) $page->header('Cache-Control'), 'no-store') && str_contains($page->body(), '&lt;script&gt;collect()&lt;/script&gt;') && ! str_contains($page->body(), '<script>collect()</script>'), $label.'consulta privada escapa resposta clínica');
        $context->run($tenant, function () use ($record, $user, $label): void {
            $history = $record->snapshot();
            ($this->verify)($history['actor_id'] === (int) $user->id && $history['answer']['version'] === 1
                && ! str_contains($record->answers()->sole()->getRawOriginal('answer_encrypted'), 'Resposta sintética') && ! str_contains($record->getRawOriginal('context_encrypted'), 'request_hash'), $label.'resposta e evidência cifradas com autoria data origem e snapshot');
        });
        ($this->verify)($post($base, $input)->status() === 302 && $context->run($tenant, fn () => $visit->healthRecords()->count() === 1), $label.'reenvio clínico recupera o mesmo registro sem duplicar');
        ($this->verify)($post($base, ['answer' => 'Outra resposta sintética'] + $input)->status() === 422, $label.'UUID clínico com conteúdo diferente recusado');
        $response = $post($questionPath.'/'.$question->id.'/versions', ['label' => 'Pergunta sintética versão dois']);
        $version = $context->run($tenant, fn () => $group->questions()->orderByDesc('version')->firstOrFail());
        $this->remember($tenant, 'health_questions', (int) $version->id);
        ($this->verify)($response->status() === 302 && $version->version === 2, $label.'nova pergunta publicada sem sobrescrever versão usada');
        $page = $request('GET', $path);
        ($this->verify)($page->ok() && str_contains($page->body(), 'versão 1') && ! str_contains($page->body(), 'Pergunta sintética versão dois'), $label.'resposta histórica mantém definição da versão original');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', HealthRecord::class)->where('subject_id', $record->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'health_answer_collected')->count() === 1 && $events->where('event', 'health_collection_replayed')->count() === 1 && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria de coleta sem conteúdo clínico ou UUID bruto');
    }

    private function exerciseExecutionCompletion(Tenant $tenant, User $user, Patient $patient, Visit $visit, ExamExecution $attempt, Closure $request, Closure $post, string $label): void
    {
        $context = app(TenantContext::class);
        $base = '/patients/'.$patient->public_id.'/visits/'.$visit->public_id.'/executions';
        $path = $base.'/'.$attempt->id.'/complete';
        $input = ['expected_status' => 'in_progress', 'idempotency_key' => (string) Str::uuid()];
        ($this->verify)($post($path, $input)->status() === 403, $label.'realizar exame não concede concluir sem direito explícito');
        $context->run($tenant, fn () => $user->givePermissionTo('executions.complete'));
        ($this->verify)($request('POST', $path, $input)->status() === 419, $label.'CSRF real protege conclusão da execução');
        ($this->verify)($post($path, ['finished_at' => '2026-12-12 10:00:00'] + $input)->status() === 422, $label.'conclusão não aceita horário de término injetado');
        ($this->verify)($post($path, $input)->status() === 302, $label.'executor conclui tentativa por HTTP com concessão vigente');
        $context->run($tenant, function () use ($attempt, $visit, $label): void {
            $fresh = $attempt->fresh();
            $history = $fresh->preparationSnapshot();
            $order = $visit->order()->firstOrFail();
            ($this->verify)($fresh->status === 'completed' && $fresh->finished_at !== null && $history['completion']['professional_id'] === (int) $fresh->performed_by
                && $order->status === 'completed' && $order->items()->sole()->status === 'completed' && $visit->fresh()->status === 'in_progress'
                && $order->contextSnapshot()['completion']['execution_id'] === (int) $fresh->id, $label.'tentativa item e pedido concluídos com evidência sem encerrar atendimento');
        });
        $page = $request('GET', $base);
        ($this->verify)($page->ok() && str_contains($page->body(), 'Execução concluída') && ! str_contains($page->body(), 'Concluir execução'), $label.'consulta histórica reconhece término sem repetir ação');
        ($this->verify)($post($path, $input)->status() === 302 && $post($path, ['idempotency_key' => (string) Str::uuid()] + $input)->status() === 422, $label.'reenvio de conclusão recuperado e nova chave recusada');
        $record = $context->run($tenant, fn () => $visit->healthRecords()->sole());
        $page = $request('GET', '/patients/'.$patient->public_id.'/visits/'.$visit->public_id.'/health-records/'.$record->public_id);
        ($this->verify)($page->ok() && str_contains($page->body(), 'versão 1'), $label.'resposta clínica histórica permanece legível após concluir pedido');
        $events = DB::table('activity_log')->where('tenant_id', $tenant->id)->where('subject_type', ExamExecution::class)->where('subject_id', $attempt->id)->get(['event', 'properties']);
        ($this->verify)($events->where('event', 'execution_completed')->count() === 1 && $events->where('event', 'execution_completion_replayed')->count() === 1
            && $events->pluck('properties')->filter()->isEmpty(), $label.'auditoria única de conclusão e reenvio sem conteúdo clínico');
    }

    private function cleanup(): void
    {
        app(LocalHomologation::class)->ensure();
        DB::transaction(function (): void {
            foreach ($this->fixtures as $tenantId => $fixture) {
                $orders = $fixture['exam_orders'] ?? [];
                DB::table('professional_exam_authorizations')->where('tenant_id', $tenantId)->whereIn('professional_id', $fixture['professionals'] ?? [])->delete();
                if (DB::table('professional_exam_authorizations')->where('tenant_id', $tenantId)->whereIn('professional_id', $fixture['professionals'] ?? [])->exists()) {
                    throw new RuntimeException('Professional authorization cleanup incomplete.');
                }
                $visitIds = DB::table('visits')->where('tenant_id', $tenantId)->whereIn('appointment_id', $fixture['appointments'] ?? [])->pluck('id');
                $recordIds = DB::table('health_records')->where('tenant_id', $tenantId)->whereIn('visit_id', $visitIds)->pluck('id');
                DB::table('health_answers')->where('tenant_id', $tenantId)->whereIn('health_record_id', $recordIds)->delete();
                DB::table('health_records')->where('tenant_id', $tenantId)->whereIn('id', $recordIds)->delete();
                if (DB::table('health_records')->where('tenant_id', $tenantId)->whereIn('id', $recordIds)->exists() || DB::table('health_answers')->where('tenant_id', $tenantId)->whereIn('health_record_id', $recordIds)->exists()) {
                    throw new RuntimeException('Clinical fixture cleanup incomplete.');
                }
                DB::table('exam_executions')->where('tenant_id', $tenantId)->whereIn('visit_id', $visitIds)->delete();
                if (DB::table('exam_executions')->where('tenant_id', $tenantId)->whereIn('visit_id', $visitIds)->exists()) {
                    throw new RuntimeException('Execution fixture cleanup incomplete.');
                }
                DB::table('visits')->where('tenant_id', $tenantId)->whereIn('appointment_id', $fixture['appointments'] ?? [])->delete();
                if (DB::table('visits')->where('tenant_id', $tenantId)->whereIn('appointment_id', $fixture['appointments'] ?? [])->exists()) {
                    throw new RuntimeException('Visit fixture cleanup incomplete.');
                }
                DB::table('clinical_receivables')->where('tenant_id', $tenantId)->whereIn('exam_order_id', $orders)->delete();
                if (DB::table('clinical_receivables')->where('tenant_id', $tenantId)->whereIn('exam_order_id', $orders)->exists()) {
                    throw new RuntimeException('Receivable fixture cleanup incomplete.');
                }
                DB::table('exam_order_items')->where('tenant_id', $tenantId)->whereIn('exam_order_id', $orders)->delete();
                foreach (['model_has_permissions', 'model_has_roles'] as $table) {
                    DB::table($table)->where('tenant_id', $tenantId)->where('model_type', User::class)->whereIn('model_id', $fixture['users'] ?? [])->delete();
                }
                DB::table('sessions')->where('tenant_id', $tenantId)->whereIn('user_id', $fixture['users'] ?? [])->delete();
                foreach (['health_questions', 'health_question_groups', 'appointments', 'exam_orders', 'patients', 'exam_types', 'companies', 'professionals', 'users', 'rooms', 'units'] as $table) {
                    DB::table($table)->where('tenant_id', $tenantId)->whereIn('id', $fixture[$table] ?? [])->delete();
                    if (DB::table($table)->where('tenant_id', $tenantId)->whereIn('id', $fixture[$table] ?? [])->exists()) {
                        throw new RuntimeException('Order fixture cleanup incomplete.');
                    }
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ($this->verify)(true, 'Pedidos: fixtures, itens, concessões e sessões temporárias removidos; auditoria preservada');
    }
}
