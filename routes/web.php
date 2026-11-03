<?php

declare(strict_types=1);

use App\Http\Controllers\CompanyAccessController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyUnitController;
use App\Http\Controllers\EmploymentController;
use App\Http\Controllers\MemberUnitController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::resource('units', UnitController::class)->scoped(['unit' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::get('/professionals/register/{user:public_id}', [ProfessionalController::class, 'registration'])->middleware('clinical.2fa')->name('professionals.registration');
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
    Route::resource('patients', PatientController::class)->scoped(['patient' => 'public_id'])->except('destroy')->middleware('clinical.2fa');
    Route::get('/security', SecurityController::class)->middleware('password.confirm')->name('security');
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->middleware('password.confirm')->name('sessions.destroy');
    Route::get('/users/{user:public_id}/unit', [MemberUnitController::class, 'edit'])->middleware(['clinical.2fa', 'password.confirm'])->name('users.unit.edit');
    Route::put('/users/{user:public_id}/unit', [MemberUnitController::class, 'update'])->middleware(['clinical.2fa', 'password.confirm'])->name('users.unit.update');
    Route::get('/users', [UserController::class, 'index'])->middleware('clinical.2fa')->name('users.index');
    Route::get('/users/{user:public_id}', [UserController::class, 'show'])->middleware('clinical.2fa')->name('users.show');
    Route::view('/', 'dashboard')->middleware('clinical.2fa')->name('dashboard');
});
