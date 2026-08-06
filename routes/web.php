<?php

use App\Http\Controllers\Api\GroupeApiController;
use App\Http\Controllers\Api\PlatformApiController;
use App\Http\Controllers\Api\ReasonTemplateApiController;
use App\Http\Controllers\Api\ReferenceApiController;
use App\Http\Controllers\Api\TeamApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\SpaController;
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
        /*
        | Endpoints de l'interface. Servis par la session web et non par un
        | jeton : ils ne sont utilisés que par la SPA, jamais par un tiers.
        */
        Route::prefix('api')->group(function (): void {
            Route::get('/groupes', [GroupeApiController::class, 'index']);
            Route::post('/groupes/{groupe}/block', [GroupeApiController::class, 'block']);
            Route::post('/groupes/{groupe}/unblock', [GroupeApiController::class, 'unblock']);
            Route::get('/groupes/{groupe}/actions', [GroupeApiController::class, 'actions']);
            Route::get('/reason-templates', [ReferenceApiController::class, 'templates']);
            Route::post('/sync', [ReferenceApiController::class, 'sync']);

            /*
            | Administration : comptes, plateformes et motifs types.
            | Suspendre un accès reste ouvert à toute l'équipe ; seule la
            | configuration est réservée aux administrateurs.
            */
            Route::middleware('admin')->group(function (): void {
                Route::get('/team', [TeamApiController::class, 'index']);
                Route::post('/team', [TeamApiController::class, 'store']);
                Route::put('/team/{user}', [TeamApiController::class, 'update']);
                Route::delete('/team/{user}', [TeamApiController::class, 'destroy']);
                Route::post('/team/{user}/reset-password', [TeamApiController::class, 'resetPassword']);

                Route::get('/platforms', [PlatformApiController::class, 'index']);
                Route::post('/platforms', [PlatformApiController::class, 'store']);
                Route::put('/platforms/{platform}', [PlatformApiController::class, 'update']);
                Route::delete('/platforms/{platform}', [PlatformApiController::class, 'destroy']);
                Route::post('/platforms/{platform}/test', [PlatformApiController::class, 'test']);

                Route::post('/reason-templates', [ReasonTemplateApiController::class, 'store']);
                Route::put('/reason-templates/{template}', [ReasonTemplateApiController::class, 'update']);
                Route::delete('/reason-templates/{template}', [ReasonTemplateApiController::class, 'destroy']);
            });
        });

        // Toutes les autres URL sont rendues par la SPA.
        Route::get('/{any?}', SpaController::class)
            ->where('any', '^(?!api|login|logout|mot-de-passe).*')
            ->name('groupes.index');
    });
});
