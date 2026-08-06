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

        @error('current_password')
        <p class="error">{{ $message }}</p>
        @enderror

        <label for="current_password">Mot de passe reçu</label>
        <input id="current_password" type="password" name="current_password" required autofocus
               autocomplete="current-password">

        @error('password')
        <p class="error">{{ $message }}</p>
        @enderror

        <label for="password">Nouveau mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">Confirmez le nouveau mot de passe</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required
               autocomplete="new-password">

        <button type="submit">Enregistrer</button>
    </form>
@endsection
