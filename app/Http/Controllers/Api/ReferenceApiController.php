<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockReasonTemplate;
use App\Services\GroupeSynchronizer;
use App\Services\SyncResult;
use Illuminate\Http\JsonResponse;

class ReferenceApiController extends Controller
{
    public function templates(): JsonResponse
    {
        return response()->json([
            'data' => BlockReasonTemplate::query()
                ->orderBy('position')
                ->get()
                ->map(fn (BlockReasonTemplate $template): array => [
                    'id' => $template->id,
                    'label' => $template->label,
                    'body_fr' => $template->body_fr,
                    'body_en' => $template->body_en,
                ]),
        ]);
    }

    /**
     * Rafraîchit le miroir à la demande.
     *
     * Une plateforme injoignable n'est pas une erreur bloquante : les autres
     * sont mises à jour et l'écran rapporte plateforme par plateforme.
     */
    public function sync(GroupeSynchronizer $synchronizer): JsonResponse
    {
        $results = $synchronizer->syncAll();

        return response()->json([
            'data' => $results->map(fn (SyncResult $result): array => [
                'platform' => $result->platform->name,
                'success' => $result->success,
                'message' => $result->message(),
            ])->values(),
            'all_succeeded' => $results->every(fn (SyncResult $result): bool => $result->success),
        ]);
    }
}
