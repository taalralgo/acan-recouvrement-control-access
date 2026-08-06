<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Groupe;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran principal : la liste des entreprises.
 *
 * Volontairement minimal au lot 2, le temps que le socle soit éprouvé.
 * L'interface Vuetify du lot 3 viendra s'y substituer.
 */
class GroupeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $groupes = Groupe::query()
            ->with('platform')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('groupes.index', [
            'groupes' => $groupes,
            'search' => $search,
        ]);
    }
}
