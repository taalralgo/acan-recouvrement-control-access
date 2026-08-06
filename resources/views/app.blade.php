<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>blockAccess</title>
    @vite('resources/js/app.js')
</head>
<body>
<div id="app"
     data-user-name="{{ auth()->user()->name }}"
     data-user-email="{{ auth()->user()->email }}"
     data-user-admin="{{ auth()->user()->isAdmin() ? '1' : '0' }}"
     data-logout-url="{{ route('logout') }}"></div>
</body>
</html>
