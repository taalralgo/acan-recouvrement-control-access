<?php

declare(strict_types=1);

namespace App\Support;

use Random\Randomizer;

/**
 * Mot de passe temporaire, transmis de vive voix ou par messagerie interne.
 *
 * Aucun email n'étant envoyé, il est lu à voix haute ou recopié : on écarte
 * donc les caractères qui se confondent (0/O, 1/l/I) plutôt que de maximiser
 * l'entropie théorique. Le mot de passe ne vit que jusqu'à la première
 * connexion, où il doit être remplacé.
 */
final class TemporaryPassword
{
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 14;

    public static function generate(): string
    {
        $randomizer = new Randomizer();
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;

        $password = '';

        for ($i = 0; $i < self::LENGTH; $i++)
        {
            $password .= $alphabet[$randomizer->getInt(0, $max)];
        }

        return $password;
    }
}
