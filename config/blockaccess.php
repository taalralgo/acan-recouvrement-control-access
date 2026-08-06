<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Comptes de l'équipe
    |--------------------------------------------------------------------------
    */

    // Seul domaine autorisé pour les comptes. Empêche la création d'un accès
    // sur une adresse personnelle, qui survivrait au départ de son titulaire.
    'email_domain' => env('BLOCKACCESS_EMAIL_DOMAIN', 'acan.email'),

    /*
    |--------------------------------------------------------------------------
    | Appels aux plateformes
    |--------------------------------------------------------------------------
    */

    'http' => [
        // Les plateformes sont sur d'autres serveurs : une injoignable ne doit
        // jamais faire attendre l'équipe devant un écran figé.
        'timeout' => (int) env('BLOCKACCESS_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('BLOCKACCESS_HTTP_CONNECT_TIMEOUT', 5),
    ],

    // Longueur maximale du motif, alignée sur ce qu'acceptent les plateformes.
    'reason_max_length' => 500,

    // Au-delà de ce délai, la liste locale est signalée comme datée à l'écran.
    'stale_after_minutes' => (int) env('BLOCKACCESS_STALE_AFTER_MINUTES', 60),

];
