@extends('layouts.app')

@section('title', 'Entreprises — blockAccess')

@section('content')
    <header style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
        <h1>Entreprises</h1>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Se déconnecter</button>
        </form>
    </header>

    <form method="GET">
        <input type="text" name="search" value="{{ $search }}" placeholder="Rechercher une entreprise…">
        <button type="submit">Rechercher</button>
    </form>

    <table>
        <thead>
        <tr>
            <th>Entreprise</th>
            <th>Plateforme</th>
            <th>Utilisateurs</th>
            <th>Accès</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($groupes as $groupe)
            <tr>
                <td>
                    {{ $groupe->name }}
                    @if ($groupe->code)
                        <span class="muted">{{ $groupe->code }}</span>
                    @endif
                </td>
                <td>{{ $groupe->platform->name }}</td>
                <td>{{ $groupe->users_count }}</td>
                <td>
                    @if ($groupe->is_blocked)
                        <span class="badge badge-blocked">
                            Bloqué depuis le {{ $groupe->blocked_at?->format('d/m/Y') }}
                        </span>
                    @else
                        <span class="badge badge-active">Actif</span>
                    @endif

                    {{-- Second verrou, décidé par les admins de la plateforme.
                         L'afficher évite qu'un déblocage semble sans effet. --}}
                    @if ($groupe->isDisabledByPlatform())
                        <span class="badge badge-disabled">Désactivé sur la plateforme</span>
                    @endif

                    @if ($groupe->isStale())
                        <div class="muted">Information datée, actualisez la liste</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="muted">
                    Aucune entreprise. Lancez <code>php artisan blockaccess:sync</code>.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    {{ $groupes->links() }}
@endsection
