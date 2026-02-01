@extends('layouts.app')

@section('title', 'Bienvenue sur Akasi Group')

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="welcome-message">
    <h2>Bienvenue sur Akasi Group</h2>
    <p>Nous sommes ravis de vous accueillir ! Commençons par configurer votre compte.</p>
</div>

<div style="display: flex; flex-direction: column; gap: 15px;">
    <form action="{{ route('onboarding.start') }}" method="GET" style="margin: 0;">
        <button type="submit" class="btn btn-primary">
            Démarrer
        </button>
    </form>
    
    <a href="{{ route('root.login') }}" class="btn" style="background: white; color: #667eea; border: 2px solid #667eea; text-decoration: none; display: block; text-align: center;">
        Se connecter
    </a>
</div>
@endsection
