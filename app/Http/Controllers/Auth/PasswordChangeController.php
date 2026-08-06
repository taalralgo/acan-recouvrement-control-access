<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Changement du mot de passe temporaire à la première connexion.
 *
 * L'admin ayant vu le mot de passe qu'il a transmis, le remplacer est la seule
 * façon que la personne soit ensuite seule à connaître le sien — ce qui donne
 * son sens au caractère nominatif du journal.
 */
class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password))
        {
            throw ValidationException::withMessages([
                'current_password' => 'Mot de passe actuel incorrect.',
            ]);
        }

        if (Hash::check($validated['password'], $user->password))
        {
            throw ValidationException::withMessages([
                'password' => 'Choisissez un mot de passe différent du mot de passe temporaire.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('groupes.index')
            ->with('status', 'Mot de passe mis à jour.');
    }
}
