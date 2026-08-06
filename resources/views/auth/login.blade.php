@extends('layouts.app')

@section('title', 'Connexion — aCAN Régie')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="card">
        @csrf
        <h1>aCAN Régie</h1>

        @error('email')
        <p class="error">{{ $message }}</p>
        @enderror

        <label for="email">Adresse email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
               autocomplete="username">

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Se connecter</button>
    </form>
@endsection
