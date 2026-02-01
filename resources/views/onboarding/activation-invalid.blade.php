@extends('layouts.app')

@section('title', 'Lien invalide - Akasi Group')

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="welcome-message" style="text-align: center;">
    <h2 style="color: #e74c3c;">Lien d'activation invalide</h2>
    <p style="margin-top: 20px; color: #666;">
        Le lien d'activation que vous avez utilisé n'est pas valide ou a déjà été utilisé.
    </p>
    <p style="margin-top: 15px; color: #666;">
        Si vous avez déjà activé votre compte, vous pouvez vous connecter directement.
    </p>
    <div style="margin-top: 30px;">
        <a href="{{ route('onboarding.welcome') }}" class="btn btn-primary">
            Retour à l'accueil
        </a>
    </div>
</div>
@endsection
