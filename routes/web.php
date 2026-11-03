<?php

declare(strict_types=1);

use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/security', SecurityController::class)->middleware('password.confirm')->name('security');
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->middleware('password.confirm')->name('sessions.destroy');
    Route::get('/users', [UserController::class, 'index'])->middleware('clinical.2fa')->name('users.index');
    Route::get('/users/{user:public_id}', [UserController::class, 'show'])->middleware('clinical.2fa')->name('users.show');
    Route::view('/', 'dashboard')->middleware('clinical.2fa')->name('dashboard');
});
