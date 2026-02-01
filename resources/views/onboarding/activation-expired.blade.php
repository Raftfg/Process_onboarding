@extends('layouts.app')

@section('title', 'Lien expiré - Akasi Group')

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="welcome-message" style="text-align: center;">
    <h2 style="color: #e74c3c;">Lien d'activation expiré</h2>
    <p style="margin-top: 20px; color: #666;">
        Le lien d'activation que vous avez utilisé a expiré. Les liens d'activation sont valides pendant {{ config('app.activation_token_expires_days', 7) }} {{ config('app.activation_token_expires_days', 7) > 1 ? 'jours' : 'jour' }}.
    </p>
    <p style="margin-top: 15px; color: #666;">
        Veuillez contacter le support pour obtenir un nouveau lien d'activation.
    </p>
    <div style="margin-top: 30px;">
        <a href="{{ route('onboarding.welcome') }}" class="btn btn-primary">
            Retour à l'accueil
        </a>
    </div>
</div>
@endsection
