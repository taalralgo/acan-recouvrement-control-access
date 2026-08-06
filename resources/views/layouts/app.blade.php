<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'aCAN Régie')</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem; line-height: 1.5; }
        .wrap { max-width: 60rem; margin: 0 auto; }
        .card { border: 1px solid rgba(128,128,128,.35); border-radius: .5rem; padding: 1.5rem; max-width: 26rem; margin: 3rem auto; }
        label { display: block; margin-block: .75rem .25rem; font-weight: 600; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: .5rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: .5rem 1rem; cursor: pointer; }
        .error { color: #b3261e; margin: .25rem 0; }
        .status { color: #1b5e20; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid rgba(128,128,128,.25); }
        .badge { padding: .1rem .5rem; border-radius: 1rem; font-size: .8rem; white-space: nowrap; }
        .badge-blocked { background: #fce8e6; color: #b3261e; }
        .badge-active { background: #e6f4ea; color: #1b5e20; }
        .badge-disabled { background: #eee; color: #555; }
        .muted { color: #777; font-size: .85rem; }
    </style>
</head>
<body>
<div class="wrap">
    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    @yield('content')
</div>
</body>
</html>
