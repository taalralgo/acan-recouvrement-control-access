<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\CompanyEmail;
use App\Support\TemporaryPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comptes de l'équipe interne.
 *
 * Le départ d'un employé est le cas qui structure cet écran : son compte doit
 * pouvoir disparaître complètement, sans rendre l'historique illisible pour
 * autant — d'où les instantanés conservés dans le journal.
 */
class TeamApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->present($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email'), new CompanyEmail()],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_COLLECTOR])],
        ]);

        $password = TemporaryPassword::generate();

        $user = User::create([
            ...$validated,
            'password' => $password,
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => "Compte créé pour {$user->name}.",
            'data' => $this->present($user),
            // Affiché une seule fois : l'admin le transmet de vive voix, et
            // l'intéressé doit le remplacer à sa première connexion.
            'temporary_password' => $password,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user), new CompanyEmail()],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_COLLECTOR])],
        ]);

        if ($validated['role'] !== User::ROLE_ADMIN && $user->isLastAdmin())
        {
            return $this->refuse('Ce compte est le dernier administrateur : conservez-lui son rôle ou nommez d\'abord un autre administrateur.');
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Compte mis à jour.',
            'data' => $this->present($user->refresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user()))
        {
            // Se supprimer soi-même déconnecterait l'admin en pleine action,
            // sans bénéfice : un autre administrateur s'en charge.
            return $this->refuse('Vous ne pouvez pas supprimer votre propre compte.');
        }

        // Garde-fou de second rideau : dans le flux actuel elle est
        // inatteignable (un administrateur qui en supprime un autre en laisse
        // forcément un), mais elle deviendrait la seule protection si
        // l'auto-suppression venait à être autorisée.
        if ($user->isLastAdmin())
        {
            return $this->refuse('Ce compte est le dernier administrateur : nommez d\'abord un autre administrateur.');
        }

        $name = $user->name;
        $user->delete();

        return response()->json([
            'message' => "Le compte de {$name} a été supprimé. L'historique de ses décisions est conservé.",
        ]);
    }

    /**
     * Régénère un mot de passe temporaire.
     *
     * Il n'y a pas de « mot de passe oublié » en autonomie : sans envoi
     * d'email, c'est l'administrateur qui remet la personne en selle.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user()))
        {
            // Le geste n'a de sens que pour dépanner quelqu'un d'autre. Se
            // l'appliquer à soi-même repose `must_change_password` et ferme
            // aussitôt l'accès : sans le mot de passe affiché, un
            // administrateur unique se retrouverait enfermé dehors.
            return $this->refuse(
                'Ce bouton sert à dépanner un collègue. Pour changer votre propre mot de passe, utilisez « Mon mot de passe ».'
            );
        }

        $password = TemporaryPassword::generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        return response()->json([
            'message' => "Nouveau mot de passe temporaire pour {$user->name}.",
            'data' => $this->present($user),
            'temporary_password' => $password,
        ]);
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_CONFLICT);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'must_change_password' => $user->must_change_password,
            'is_last_admin' => $user->isLastAdmin(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
