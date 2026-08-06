<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve la gestion des comptes, des plateformes et des modèles de motifs aux
 * administrateurs. Suspendre et rétablir un accès reste ouvert à toute l'équipe.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() !== true)
        {
            abort(Response::HTTP_FORBIDDEN, 'Réservé aux administrateurs.');
        }

        return $next($request);
    }
}
