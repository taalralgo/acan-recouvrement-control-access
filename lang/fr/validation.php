<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messages de validation
|--------------------------------------------------------------------------
|
| Laravel ne fournit aucune traduction française : sans ce fichier, les
| formulaires affichent la clé brute (« validation.min.string »), ce qui est
| incompréhensible pour l'équipe. Seules les règles réellement utilisées par
| l'application sont traduites ; les autres restent disponibles en ajoutant
| leur clé ici.
|
*/

return [

    'accepted' => 'Le champ :attribute doit être accepté.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'ends_with' => 'Le champ :attribute doit se terminer par : :values.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'url' => 'Le champ :attribute doit être une adresse web valide.',

    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas dépasser :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],

    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit faire au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],

    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données. Choisissez-en un autre.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages sur mesure
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'password' => [
            'min' => 'Choisissez un mot de passe d\'au moins :min caractères.',
            'confirmed' => 'Les deux mots de passe saisis ne sont pas identiques.',
        ],
        'current_password' => [
            'required' => 'Saisissez le mot de passe qui vous a été transmis.',
        ],
        'reason' => [
            'required' => 'Un motif est obligatoire : il sera lu par le client.',
        ],
        'api_token' => [
            'required' => 'Le jeton fourni par la plateforme est obligatoire.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Noms des champs
    |--------------------------------------------------------------------------
    |
    | Remplacent le nom technique dans les messages : « Le champ email est
    | obligatoire » devient « Le champ adresse email est obligatoire ».
    |
    */

    'attributes' => [
        'api_token' => 'jeton d\'accès',
        'base_url' => 'adresse',
        'body_en' => 'message en anglais',
        'body_fr' => 'message en français',
        'current_password' => 'mot de passe reçu',
        'email' => 'adresse email',
        'label' => 'nom du motif',
        'name' => 'nom',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'reason' => 'motif',
        'role' => 'rôle',
        'search' => 'recherche',
    ],

];
