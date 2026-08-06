<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\GroupeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Accessible avec un mot de passe temporaire : c'est justement l'écran qui
    // permet d'en sortir.
    Route::get('/mot-de-passe', [PasswordChangeController::class, 'edit'])->name('password.change.edit');
    Route::put('/mot-de-passe', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/', [GroupeController::class, 'index'])->name('groupes.index');
    });
});
