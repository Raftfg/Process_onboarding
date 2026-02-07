@extends('layouts.app')

@section('title', trans('onboarding.activation_invalid') . ' - ' . config('app.brand_name'))

@section('content')
<div class="logo">
    <h1>{{ config('app.brand_name') }}</h1>
</div>

<div class="welcome-message" style="text-align: center;">
    <h2 style="color: #e74c3c;">{{ trans('onboarding.activation_invalid') }}</h2>
    <p style="margin-top: 20px; color: #666;">
        {{ trans('onboarding.activation_invalid_message') }}
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
