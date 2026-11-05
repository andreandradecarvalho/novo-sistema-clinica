<?php

declare(strict_types=1);

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClinicalReceivableController;
use App\Http\Controllers\CompanyAccessController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyUnitController;
use App\Http\Controllers\EmploymentController;
use App\Http\Controllers\ExamAttachmentController;
use App\Http\Controllers\ExamExecutionController;
use App\Http\Controllers\ExamOrderController;
use App\Http\Controllers\ExamPreparationController;
use App\Http\Controllers\ExamTypeController;
use App\Http\Controllers\HealthQuestionController;
use App\Http\Controllers\HealthRecordController;
use App\Http\Controllers\MemberUnitController;
use App\Http\Controllers\OccupationalProgramController;
use App\Http\Controllers\OccupationalProtocolController;
use App\Http\Controllers\PatientConsentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientMergeController;
use App\Http\Controllers\PatientReviewController;
use App\Http\Controllers\PatientSearchController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\ProfessionalExamAuthorizationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::prefix('patients/{patient:public_id}/visits/{visit:public_id}/health-records')->name('patients.visits.health-records.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [HealthRecordController::class, 'index'])->name('index');
        Route::get('/create', [HealthRecordController::class, 'create'])->name('create');
        Route::post('/', [HealthRecordController::class, 'store'])->middleware('password.confirm')->name('store');
        Route::get('/{healthRecord:public_id}', [HealthRecordController::class, 'show'])->name('show');
    });
    Route::prefix('patients/{patient:public_id}/visits/{visit:public_id}/executions')->name('patients.visits.executions.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::post('{execution}/complete', [ExamExecutionController::class, 'complete'])->middleware('password.confirm')->name('complete');
        Route::post('{execution}/start', [ExamExecutionController::class, 'start'])->middleware('password.confirm')->name('start');
        Route::get('/', [ExamExecutionController::class, 'index'])->name('index');
        Route::post('/', [ExamExecutionController::class, 'store'])->name('store');
        Route::post('/{execution}/cancel', [ExamExecutionController::class, 'cancel'])->name('cancel');
        Route::get('/{execution}/room', [ExamExecutionController::class, 'room'])->name('room');
        Route::post('/{execution}/room', [ExamExecutionController::class, 'assignRoom'])->name('room.store');
    });
    Route::get('patients/{patient:public_id}/visits/{visit:public_id}', [VisitController::class, 'show'])->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->name('patients.visits.show');
    Route::post('patients/{patient:public_id}/visits/{visit:public_id}/cancel', [VisitController::class, 'cancel'])->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->name('patients.visits.cancel');
    Route::prefix('patients/{patient:public_id}/appointments')->name('patients.appointments.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');
        Route::get('/create', [AppointmentController::class, 'create'])->name('create');
        Route::post('/', [AppointmentController::class, 'store'])->name('store');
        Route::get('/{appointment:public_id}', [AppointmentController::class, 'show'])->name('show');
        Route::post('/{appointment:public_id}/confirm', [AppointmentController::class, 'confirm'])->name('confirm');
        Route::get('/{appointment:public_id}/check-in', [VisitController::class, 'create'])->name('check-in.create');
        Route::post('/{appointment:public_id}/check-in', [VisitController::class, 'store'])->name('check-in.store');
        Route::post('/{appointment:public_id}/cancel', [AppointmentController::class, 'cancel'])->name('cancel');
    });
    Route::prefix('patients/{patient:public_id}/orders')->name('patients.orders.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [ExamOrderController::class, 'index'])->name('index');
        Route::get('/create', [ExamOrderController::class, 'create'])->name('create');
        Route::post('/', [ExamOrderController::class, 'store'])->name('store');
        Route::get('/payers', [ExamOrderController::class, 'payers'])->name('payers');
        Route::get('/company/{company:public_id}/create', [ExamOrderController::class, 'createCompany'])->withoutScopedBindings()->name('company.create');
        Route::post('/company/{company:public_id}', [ExamOrderController::class, 'storeCompany'])->withoutScopedBindings()->name('company.store');
        Route::post('/{order:public_id}/receivables/{receivable}/settle', [ClinicalReceivableController::class, 'settle'])->name('receivables.settle');
        Route::post('/{order:public_id}/receivables/{receivable}/cancel', [ClinicalReceivableController::class, 'cancel'])->name('receivables.cancel');
        Route::get('/{order:public_id}/receivables', [ClinicalReceivableController::class, 'index'])->name('receivables.index');
        Route::post('/{order:public_id}/receivables', [ClinicalReceivableController::class, 'store'])->name('receivables.store');
        Route::get('/{order:public_id}', [ExamOrderController::class, 'show'])->name('show');
        Route::post('/{order:public_id}/authorize', [ExamOrderController::class, 'authorize'])->name('authorize');
        Route::post('/{order:public_id}/cancel', [ExamOrderController::class, 'cancel'])->name('cancel');
    });
    Route::prefix('programs/{program}/protocols')->name('programs.protocols.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [OccupationalProtocolController::class, 'index'])->name('index');
        Route::get('/create', [OccupationalProtocolController::class, 'create'])->name('create');
        Route::post('/', [OccupationalProtocolController::class, 'store'])->name('store');
        Route::get('/{protocol}', [OccupationalProtocolController::class, 'show'])->name('show');
        Route::get('/{protocol}/versions', [OccupationalProtocolController::class, 'version'])->name('version');
        Route::post('/{protocol}/versions', [OccupationalProtocolController::class, 'replace'])->name('replace');
        Route::post('/{protocol}/approve', [OccupationalProtocolController::class, 'approve'])->middleware('password.confirm')->name('approve');
    });
    Route::prefix('companies/{company:public_id}/programs')->name('companies.programs.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [OccupationalProgramController::class, 'index'])->name('index');
        Route::get('/create', [OccupationalProgramController::class, 'create'])->name('create');
        Route::get('/register/{professional}', [OccupationalProgramController::class, 'registration'])->withoutScopedBindings()->name('registration');
        Route::post('/register/{professional}', [OccupationalProgramController::class, 'store'])->withoutScopedBindings()->name('store');
        Route::get('/{program}', [OccupationalProgramController::class, 'show'])->name('show');
    });
    Route::prefix('patients/{patient:public_id}/consents')->name('patients.consents.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [PatientConsentController::class, 'index'])->name('index');
        Route::get('/create', [PatientConsentController::class, 'create'])->name('create');
        Route::post('/', [PatientConsentController::class, 'store'])->name('store');
        Route::get('/{consent}', [PatientConsentController::class, 'show'])->name('show');
    });
    Route::prefix('questionnaires')->name('questionnaires.')->scopeBindings()->middleware('clinical.2fa')->group(function (): void {
        Route::get('/', [HealthQuestionController::class, 'index'])->name('index');
        Route::post('/', [HealthQuestionController::class, 'storeGroup'])->name('store');
        Route::get('/{group}', [HealthQuestionController::class, 'show'])->name('show');
        Route::post('/{group}/questions', [HealthQuestionController::class, 'store'])->name('questions.store');
        Route::get('/{group}/questions/{question}', [HealthQuestionController::class, 'question'])->name('questions.show');
        Route::post('/{group}/questions/{question}/versions', [HealthQuestionController::class, 'revise'])->name('questions.revise');
    });
    Route::prefix('exam-types/{exam_type:public_id}/attachments')->name('exam-types.attachments.')->scopeBindings()->middleware('clinical.2fa')->group(function (): void {
        Route::get('/', [ExamAttachmentController::class, 'index'])->name('index');
        Route::get('/create', [ExamAttachmentController::class, 'create'])->name('create');
        Route::post('/', [ExamAttachmentController::class, 'store'])->name('store');
        Route::get('/{attachment}/download', [ExamAttachmentController::class, 'download'])->name('download');
    });
    Route::prefix('exam-types/{exam_type:public_id}/preparations')->name('exam-types.preparations.')->scopeBindings()->middleware('clinical.2fa')->group(function (): void {
        Route::get('/', [ExamPreparationController::class, 'index'])->name('index');
        Route::get('/create', [ExamPreparationController::class, 'create'])->name('create');
        Route::post('/', [ExamPreparationController::class, 'store'])->name('store');
        Route::get('/{preparation}', [ExamPreparationController::class, 'show'])->name('show');
        Route::get('/{preparation}/edit', [ExamPreparationController::class, 'edit'])->name('edit');
        Route::put('/{preparation}', [ExamPreparationController::class, 'update'])->name('update');
    });
    Route::resource('exam-types', ExamTypeController::class)->scoped(['exam_type' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::resource('units.rooms', RoomController::class)->scoped(['unit' => 'public_id'])->except('destroy')->middleware(['clinical.2fa', 'cache.headers:private;no_store']);
    Route::resource('units', UnitController::class)->scoped(['unit' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::get('/professionals/register/{user:public_id}', [ProfessionalController::class, 'registration'])->middleware('clinical.2fa')->name('professionals.registration');
    Route::prefix('professionals/{professional}/exam-authorizations')->name('professionals.authorizations.')->scopeBindings()->middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/', [ProfessionalExamAuthorizationController::class, 'index'])->name('index');
        Route::post('/', [ProfessionalExamAuthorizationController::class, 'store'])->middleware('password.confirm')->name('store');
        Route::post('/{authorization}/revoke', [ProfessionalExamAuthorizationController::class, 'revoke'])->middleware('password.confirm')->name('revoke');
    });
    Route::resource('professionals', ProfessionalController::class)->except('destroy')->middleware('clinical.2fa');
    Route::prefix('companies/{company:public_id}/accesses')->name('companies.accesses.')->middleware('clinical.2fa')->group(function (): void {
        Route::get('/', [CompanyAccessController::class, 'index'])->name('index');
        Route::get('/create', [CompanyAccessController::class, 'create'])->middleware('password.confirm')->name('create');
        Route::post('/', [CompanyAccessController::class, 'store'])->middleware('password.confirm')->name('store');
        Route::post('/{access}/revoke', [CompanyAccessController::class, 'revoke'])->scopeBindings()->middleware('password.confirm')->name('revoke');
    });
    Route::resource('companies', CompanyController::class)->scoped(['company' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::resource('companies.units', CompanyUnitController::class)->parameters(['units' => 'unit'])->only(['create', 'store', 'edit', 'update'])->scoped(['company' => 'public_id'])->middleware('clinical.2fa');
    Route::prefix('patients/{patient:public_id}/employments')->name('patients.employments.')->middleware('clinical.2fa')->group(function (): void {
        Route::get('/', [EmploymentController::class, 'index'])->name('index');
        Route::get('/create', [EmploymentController::class, 'create'])->name('create');
        Route::get('/register/{company:public_id}', [EmploymentController::class, 'registration'])->withoutScopedBindings()->name('registration');
        Route::post('/register/{company:public_id}', [EmploymentController::class, 'store'])->withoutScopedBindings()->name('store');
        Route::get('/{employment}/version', [EmploymentController::class, 'version'])->scopeBindings()->name('version');
        Route::post('/{employment}/version', [EmploymentController::class, 'replace'])->scopeBindings()->name('replace');
        Route::post('/{employment}/terminate', [EmploymentController::class, 'terminate'])->scopeBindings()->name('terminate');
    });
    Route::middleware(['clinical.2fa', 'cache.headers:private;no_store'])->group(function (): void {
        Route::get('/patients/search', [PatientSearchController::class, 'create'])->name('patients.search.create');
        Route::post('/patients/search', [PatientSearchController::class, 'store'])->name('patients.search.store');
        Route::get('/patients/search/results', [PatientSearchController::class, 'results'])->name('patients.search.results');
        Route::get('/patients/{patient:public_id}/merges', [PatientMergeController::class, 'index'])->name('patients.merges');
        Route::post('/patients/{patient:public_id}/compare/{candidate:public_id}/merge', [PatientMergeController::class, 'store'])->withoutScopedBindings()->middleware('password.confirm')->name('patients.merge');
        Route::get('/patients/{patient:public_id}/duplicates', [PatientReviewController::class, 'index'])->name('patients.duplicates');
        Route::get('/patients/{patient:public_id}/compare/{candidate:public_id}', [PatientReviewController::class, 'compare'])->withoutScopedBindings()->name('patients.compare');
    });
    Route::resource('patients', PatientController::class)->scoped(['patient' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::get('/security', SecurityController::class)->middleware('password.confirm')->name('security');
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->middleware('password.confirm')->name('sessions.destroy');
    Route::get('/users/{user:public_id}/unit', [MemberUnitController::class, 'edit'])->middleware(['clinical.2fa', 'password.confirm'])->name('users.unit.edit');
    Route::put('/users/{user:public_id}/unit', [MemberUnitController::class, 'update'])->middleware(['clinical.2fa', 'password.confirm'])->name('users.unit.update');
    Route::get('/users', [UserController::class, 'index'])->middleware('clinical.2fa')->name('users.index');
    Route::get('/users/{user:public_id}', [UserController::class, 'show'])->middleware('clinical.2fa')->name('users.show');
    Route::view('/', 'dashboard')->middleware('clinical.2fa')->name('dashboard');
});
