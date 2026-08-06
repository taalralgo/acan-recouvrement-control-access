@extends('layouts.app')

@section('title', 'Choisir un mot de passe — aCAN Régie')

@section('content')
    <form method="POST" action="{{ route('password.change.update') }}" class="card">
        @csrf
        @method('PUT')

        <h1>Choisissez votre mot de passe</h1>
        <p class="muted">
            Le mot de passe qui vous a été transmis est connu de votre administrateur.
            Remplacez-le pour que vous seul y ayez accès.
        </p>

        {{-- Chaque message est rendu sous le champ qu'il concerne : placé
             au-dessus du label, il paraissait porter sur le champ précédent. --}}
        <label for="current_password">Mot de passe reçu</label>
        <input id="current_password" type="password" name="current_password" required autofocus
               autocomplete="current-password">
        @error('current_password')
        <p class="error">{{ $message }}</p>
        @enderror

        <label for="password">Nouveau mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        <p class="muted hint">Au moins 10 caractères.</p>
        @error('password')
        <p class="error">{{ $message }}</p>
        @enderror

        <label for="password_confirmation">Confirmez le nouveau mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required
               autocomplete="new-password">
        @error('password_confirmation')
        <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit">Enregistrer</button>
    </form>
@endsection
