<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>aCAN Régie</title>
    @vite('resources/js/app.js')
</head>
<body>
{{-- L'application peut être servie depuis un sous-dossier (https://…/regie).
     Le préfixe est déduit d'APP_URL et transmis à la SPA, qui l'applique à sa
     navigation et à ses appels. Vide lorsque l'application est à la racine. --}}
<div id="app"
     data-base-path="{{ rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/') }}"
     data-user-name="{{ auth()->user()->name }}"
     data-user-email="{{ auth()->user()->email }}"
     data-user-admin="{{ auth()->user()->isAdmin() ? '1' : '0' }}"
     data-logout-url="{{ route('logout') }}"></div>
</body>
</html>
