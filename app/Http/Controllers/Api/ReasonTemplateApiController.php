<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockReasonTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Motifs pré-rédigés.
 *
 * Ces textes sont lus par les clients : les faire relire et corriger ici, au
 * calme, vaut mieux que de les laisser improviser au moment de suspendre.
 */
class ReasonTemplateApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $template = BlockReasonTemplate::create($this->validateTemplate($request));

        return response()->json([
            'message' => 'Motif type ajouté.',
            'data' => $this->present($template),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, BlockReasonTemplate $template): JsonResponse
    {
        $template->update($this->validateTemplate($request));

        return response()->json([
            'message' => 'Motif type mis à jour.',
            'data' => $this->present($template->refresh()),
        ]);
    }

    public function destroy(BlockReasonTemplate $template): JsonResponse
    {
        $template->delete();

        // Les suspensions déjà prononcées gardent leur texte : le motif a été
        // copié au moment de l'action, il ne dépend pas de ce modèle.
        return response()->json(['message' => 'Motif type supprimé.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        $max = config('blockaccess.reason_max_length');

        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'body_fr' => ['required', 'string', "max:{$max}"],
            'body_en' => ['required', 'string', "max:{$max}"],
            'position' => ['integer', 'min:0', 'max:999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BlockReasonTemplate $template): array
    {
        return [
            'id' => $template->id,
            'label' => $template->label,
            'body_fr' => $template->body_fr,
            'body_en' => $template->body_en,
            'position' => $template->position,
        ];
    }
}
