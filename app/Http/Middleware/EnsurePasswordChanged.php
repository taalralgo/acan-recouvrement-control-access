<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retient l'utilisateur sur l'écran de changement tant qu'il utilise encore le
 * mot de passe temporaire transmis par l'admin.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || !$user->must_change_password)
        {
            return $next($request);
        }

        if ($request->routeIs('password.change*'))
        {
            return $next($request);
        }

        if ($request->expectsJson())
        {
            return response()->json([
                'message' => 'Vous devez d\'abord définir votre mot de passe.',
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()->route('password.change.edit');
    }
}
