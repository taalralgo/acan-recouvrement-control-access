<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaasPlatform;
use App\Saas\SaasConnector;
use App\Saas\SaasUnreachable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plateformes raccordées.
 *
 * L'adresse d'un SAAS n'est pas une constante : l'infrastructure est gérée par
 * une équipe extérieure qui déplace les projets en cas d'incident. Tout doit
 * donc être corrigeable ici, sans redéploiement ni ticket.
 */
class PlatformApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SaasPlatform::query()
                ->withCount('groupes')
                ->orderBy('name')
                ->get()
                ->map(fn (SaasPlatform $platform): array => $this->present($platform)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePlatform($request);

        $platform = SaasPlatform::create($validated);

        return response()->json([
            'message' => "Plateforme {$platform->name} enregistrée. Testez la connexion pour vérifier le raccordement.",
            'data' => $this->present($platform->loadCount('groupes')),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, SaasPlatform $platform): JsonResponse
    {
        $validated = $this->validatePlatform($request, $platform);

        // Un jeton laissé vide signifie « ne pas changer » : il n'est jamais
        // réaffiché, l'admin ne peut donc pas le ressaisir à l'identique.
        if (blank($validated['api_token'] ?? null))
        {
            unset($validated['api_token']);
        }

        $platform->update($validated);

        return response()->json([
            'message' => 'Plateforme mise à jour.',
            'data' => $this->present($platform->refresh()->loadCount('groupes')),
        ]);
    }

    public function destroy(SaasPlatform $platform): JsonResponse
    {
        $name = $platform->name;
        $platform->delete();

        return response()->json([
            'message' => "Plateforme {$name} retirée, ainsi que ses groupes en miroir.",
        ]);
    }

    /**
     * Vérifie le raccordement et rend un diagnostic exploitable.
     *
     * Trois issues doivent être distinguées : injoignable (adresse ou serveur),
     * jeton refusé, ou connexion établie. Sans ce bouton, un déménagement de
     * serveur se traduirait par une interface qui échoue sans explication.
     */
    public function test(SaasPlatform $platform, SaasConnector $connector): JsonResponse
    {
        try
        {
            $count = $connector->fetchGroupes($platform)->count();
        }
        catch (SaasUnreachable $exception)
        {
            return response()->json([
                'success' => false,
                'failure' => $exception->failure->value,
                'message' => $exception->forHumans(),
            ]);
        }

        $platform->forceFill(['last_reachable_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => "Connexion établie : {$count} groupe(s) visible(s) sur cette plateforme.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlatform(Request $request, ?SaasPlatform $platform = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'api_token' => [$platform === null ? 'required' : 'nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SaasPlatform $platform): array
    {
        return [
            'id' => $platform->id,
            'name' => $platform->name,
            'base_url' => $platform->base_url,
            'active' => $platform->active,
            'groupes_count' => $platform->groupes_count,
            'last_reachable_at' => $platform->last_reachable_at?->toIso8601String(),
            // Le jeton n'est jamais renvoyé : il n'a pas à transiter une
            // seconde fois vers le navigateur.
            'has_token' => filled($platform->getRawOriginal('api_token')),
        ];
    }
}
