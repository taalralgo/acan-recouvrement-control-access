<?php

declare(strict_types=1);

namespace App\Saas;

/**
 * Nature d'un échec côté plateforme.
 *
 * Distinguer ces cas n'est pas cosmétique : l'équipe de recouvrement n'est pas
 * technique, et « la plateforme est injoignable » appelle une réaction très
 * différente de « le plafond horaire est atteint ».
 */
enum SaasFailure: string
{
    case Unreachable = 'unreachable';
    case Unauthorized = 'unauthorized';
    case RateLimited = 'rate_limited';
    case NotFound = 'not_found';
    case Rejected = 'rejected';

    public function message(): string
    {
        return match ($this) {
            self::Unreachable => "La plateforme n'a pas répondu. Vérifiez son adresse ou réessayez dans quelques minutes.",
            self::Unauthorized => 'La plateforme a refusé la connexion. Le jeton est probablement à mettre à jour.',
            self::RateLimited => "Trop de suspensions sur cette plateforme depuis une heure. Réessayez plus tard.",
            self::NotFound => "Ce groupe n'existe plus sur la plateforme. Actualisez la liste.",
            self::Rejected => "La plateforme a refusé l'opération.",
        };
    }
}
