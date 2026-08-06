<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupeResource;
use App\Models\BlockAction;
use App\Models\Groupe;
use App\Saas\SaasUnreachable;
use App\Services\AccessSuspender;
use App\Services\GroupeSynchronizer;
use Illuminate\Support\Carbon;
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

        return GroupeResource::collection($groupes)->additional([
            'sync' => $this->syncState(),
        ]);
    }

    /**
     * Fraîcheur de la liste, tous groupes confondus.
     *
     * En v1 la synchronisation est déclenchée à la main : cette information
     * dit à l'équipe s'il faut actualiser avant d'agir. Elle est calculée sur
     * l'ensemble des groupes, pas sur la page affichée, et retient le plus
     * ancien — si une plateforme n'a pas répondu, c'est elle qui compte.
     *
     * @return array{last_at: string|null, is_stale: bool}
     */
    private function syncState(): array
    {
        $oldest = Groupe::query()->min('synced_at');

        return [
            'last_at' => $oldest === null ? null : Carbon::parse($oldest)->toIso8601String(),
            'is_stale' => $oldest === null
                || Carbon::parse($oldest)->lt(now()->subMinutes(config('regie.stale_after_minutes'))),
        ];
    }

    /**
     * Relit le groupe auprès de sa plateforme, juste avant une suspension.
     *
     * Le motif est rédigé dans la langue du groupe puis figé dans la base de
     * la plateforme jusqu'au rétablissement : le résoudre à partir d'une copie
     * datée enverrait durablement au client un message dans la mauvaise
     * langue. Le décompte d'utilisateurs annoncé à l'agent souffre du même
     * défaut. La synchronisation étant manuelle, on rafraîchit ici.
     */
    public function refresh(Groupe $groupe, GroupeSynchronizer $synchronizer): JsonResponse
    {
        $groupe->loadMissing('platform');

        $result = $synchronizer->sync($groupe->platform);

        if (!$result->success)
        {
            // Plateforme injoignable : on rend la copie connue plutôt que de
            // bloquer l'agent, en signalant qu'elle peut être datée.
            return response()->json([
                'data' => new GroupeResource($groupe),
                'refreshed' => false,
                'message' => $result->message(),
            ]);
        }

        $fresh = Groupe::query()->with('platform')->find($groupe->id);

        if ($fresh === null)
        {
            return response()->json([
                'message' => "Cette entreprise n'existe plus sur la plateforme.",
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => new GroupeResource($fresh),
            'refreshed' => true,
        ]);
    }

    public function block(Request $request, Groupe $groupe): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:' . config('regie.reason_max_length')],
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
