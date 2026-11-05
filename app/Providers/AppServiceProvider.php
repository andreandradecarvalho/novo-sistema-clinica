<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Catalog\Models\ExamAttachment;
use App\Domain\Catalog\Models\ExamPreparation;
use App\Domain\Catalog\Models\ExamType;
use App\Domain\Catalog\Models\HealthQuestionGroup;
use App\Domain\Catalog\Policies\ExamAttachmentPolicy;
use App\Domain\Catalog\Policies\ExamPreparationPolicy;
use App\Domain\Catalog\Policies\ExamTypePolicy;
use App\Domain\Catalog\Policies\HealthQuestionGroupPolicy;
use App\Domain\Clinical\Models\HealthRecord;
use App\Domain\Clinical\Policies\HealthRecordPolicy;
use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Models\CompanyAccess;
use App\Domain\Companies\Models\CompanyUnit;
use App\Domain\Companies\Policies\CompanyAccessPolicy;
use App\Domain\Companies\Policies\CompanyPolicy;
use App\Domain\Companies\Policies\CompanyUnitPolicy;
use App\Domain\Executions\Models\ExamExecution;
use App\Domain\Executions\Models\ProfessionalExamAuthorization;
use App\Domain\Executions\Policies\ExamExecutionPolicy;
use App\Domain\Executions\Policies\ProfessionalExamAuthorizationPolicy;
use App\Domain\Identity\Auth\TenantPasswordBrokerManager;
use App\Domain\Identity\Auth\TenantSessionHandler;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Identity\Services\SecurityAudit;
use App\Domain\Occupational\Models\EmploymentLink;
use App\Domain\Occupational\Models\OccupationalProgram;
use App\Domain\Occupational\Models\OccupationalProtocol;
use App\Domain\Occupational\Policies\EmploymentLinkPolicy;
use App\Domain\Occupational\Policies\OccupationalProgramPolicy;
use App\Domain\Occupational\Policies\OccupationalProtocolPolicy;
use App\Domain\Orders\Models\ClinicalReceivable;
use App\Domain\Orders\Models\ExamOrder;
use App\Domain\Orders\Policies\ClinicalReceivablePolicy;
use App\Domain\Orders\Policies\ExamOrderPolicy;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientConsent;
use App\Domain\Patients\Policies\PatientConsentPolicy;
use App\Domain\Patients\Policies\PatientPolicy;
use App\Domain\Professionals\Models\Professional;
use App\Domain\Professionals\Policies\ProfessionalPolicy;
use App\Domain\Scheduling\Models\Appointment;
use App\Domain\Scheduling\Models\Room;
use App\Domain\Scheduling\Policies\AppointmentPolicy;
use App\Domain\Scheduling\Policies\RoomPolicy;
use App\Domain\Tenancy\Exceptions\MissingTenant;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\Unit;
use App\Domain\Tenancy\Policies\UnitPolicy;
use App\Domain\Tenancy\Services\TenantContext;
use App\Domain\Visits\Models\Visit;
use App\Domain\Visits\Policies\VisitPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->extend('auth.password', fn ($manager, $app) => new TenantPasswordBrokerManager($app));
    }

    public function boot(): void
    {
        Gate::policy(HealthRecord::class, HealthRecordPolicy::class);
        Gate::policy(HealthQuestionGroup::class, HealthQuestionGroupPolicy::class);
        Gate::policy(PatientConsent::class, PatientConsentPolicy::class);
        Gate::policy(OccupationalProgram::class, OccupationalProgramPolicy::class);
        Gate::policy(OccupationalProtocol::class, OccupationalProtocolPolicy::class);
        Gate::policy(ExamOrder::class, ExamOrderPolicy::class);
        Gate::policy(ClinicalReceivable::class, ClinicalReceivablePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ExamType::class, ExamTypePolicy::class);
        Gate::policy(ExamPreparation::class, ExamPreparationPolicy::class);
        Gate::policy(ExamAttachment::class, ExamAttachmentPolicy::class);
        Gate::policy(CompanyAccess::class, CompanyAccessPolicy::class);
        Gate::policy(EmploymentLink::class, EmploymentLinkPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Professional::class, ProfessionalPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(CompanyUnit::class, CompanyUnitPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Visit::class, VisitPolicy::class);
        Gate::policy(ProfessionalExamAuthorization::class, ProfessionalExamAuthorizationPolicy::class);
        Gate::policy(ExamExecution::class, ExamExecutionPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        $this->app['session']->extend('tenant-database', fn ($app) => new TenantSessionHandler(
            $app['db']->connection(config('session.connection')), config('session.table'), (int) config('session.lifetime'), $app,
        ));
        foreach ([Login::class => 'login', Logout::class => 'logout', PasswordReset::class => 'password_reset', TwoFactorAuthenticationConfirmed::class => 'two_factor_confirmed'] as $event => $name) {
            Event::listen($event, function ($event) use ($name): void {
                app(SecurityAudit::class)->record($name, $event->user);
            });
        }
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->uncompromised());
        Queue::createPayloadUsing(fn (): array => ['tenant_id' => app(TenantContext::class)->id()]);
        Queue::before(function (JobProcessing $event): void {
            $context = app(TenantContext::class);
            $context->set(null);
            $id = $event->job->payload()['tenant_id'] ?? null;
            if ($id === null) {
                throw new MissingTenant;
            }
            $tenant = Tenant::query()->whereKey($id)->whereIn('status', ['trial', 'active', 'past_due'])->first();
            if ($tenant === null) {
                throw new MissingTenant;
            }
            $context->set($tenant);
        });
        Queue::looping(fn () => app(TenantContext::class)->set(null));
        Queue::after(fn (JobProcessed $event) => app(TenantContext::class)->set(null));
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => app(TenantContext::class)->set(null));
    }
}
