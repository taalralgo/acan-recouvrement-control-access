<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupeResource;
use App\Models\BlockAction;
use App\Models\Groupe;
use App\Saas\SaasUnreachable;
use App\Services\AccessSuspender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GroupeApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');

        $groupes = Groupe::query()
            ->with('platform')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->when($status === 'blocked', fn ($query) => $query->where('is_blocked', true))
            ->when($status === 'active', fn ($query) => $query->where('is_blocked', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return GroupeResource::collection($groupes);
    }

    public function block(Request $request, Groupe $groupe): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:' . config('blockaccess.reason_max_length')],
        ]);

        return $this->attempt(fn (): Groupe => app(AccessSuspender::class)->block(
            $groupe,
            $request->user(),
            $validated['reason'],
        ), "L'accès de {$groupe->name} a été suspendu.");
    }

    public function unblock(Request $request, Groupe $groupe): JsonResponse
    {
        return $this->attempt(fn (): Groupe => app(AccessSuspender::class)->unblock(
            $groupe,
            $request->user(),
        ), "L'accès de {$groupe->name} a été rétabli.");
    }

    public function actions(Groupe $groupe): JsonResponse
    {
        return response()->json([
            'data' => $groupe->actions()->get()->map(fn (BlockAction $action): array => [
                'id' => $action->id,
                'action' => $action->action,
                'reason' => $action->reason,
                'actor_name' => $action->actor_name,
                'actor_email' => $action->actor_email,
                'created_at' => $action->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Exécute une opération distante en traduisant l'échec en message lisible.
     *
     * L'équipe n'est pas technique : elle doit savoir si le problème vient du
     * réseau, du jeton ou d'un plafond, pas lire une trace d'exception. Et
     * surtout, un échec doit être annoncé comme tel — jamais confondu avec un
     * succès.
     *
     * @param callable(): Groupe $operation
     */
    private function attempt(callable $operation, string $successMessage): JsonResponse
    {
        try
        {
            $groupe = $operation();
        }
        catch (SaasUnreachable $exception)
        {
            return response()->json([
                'message' => $exception->forHumans(),
                'failure' => $exception->failure->value,
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'message' => $successMessage,
            'data' => new GroupeResource($groupe->load('platform')),
        ]);
    }
}
