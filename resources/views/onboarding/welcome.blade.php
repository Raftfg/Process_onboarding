@extends('layouts.app')

@section('title', 'Bienvenue sur MedKey')

@section('content')
<div class="logo">
    <h1>MedKey</h1>
</div>

<div class="welcome-message">
    <h2>Bienvenue sur MedKey</h2>
    <p>Nous sommes ravis de vous accueillir ! Commençons par configurer votre compte.</p>
</div>

<form action="{{ route('onboarding.step1') }}" method="GET">
    <button type="submit" class="btn btn-primary">
        Démarrer
    </button>
</form>
@endsection
