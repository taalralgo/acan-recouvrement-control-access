<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Point d'entrée unique de l'interface : le routage se fait ensuite côté Vue.
 */
class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('app');
    }
}
