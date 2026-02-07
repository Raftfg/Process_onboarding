@extends('layouts.app')

@section('title', trans('onboarding.create_space') . ' - ' . config('app.brand_name'))

@push('styles')
<style>
    .form-group {
        margin-bottom: 18px !important;
    }
    
    .form-group input {
        padding: 11px 15px !important;
        font-size: 15px !important;
        height: auto !important;
        border: 2px solid #e0e0e0 !important;
    }
    
    .form-group input:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
    }
    
    .form-group label {
        font-size: 13px !important;
        margin-bottom: 7px !important;
        font-weight: 500 !important;
    }
    
    .recaptcha-wrapper {
        margin: 22px 0;
    }
    
    .recaptcha-container {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        min-height: 85px;
    }
    
    .recaptcha-container > div {
        display: inline-block;
    }
    
    .welcome-message {
        margin-bottom: 28px;
    }
    
    .welcome-message h2 {
        font-size: 1.8rem !important;
        margin-bottom: 10px !important;
    }
    
    .welcome-message p {
        font-size: 14px !important;
        color: #666 !important;
        line-height: 1.5 !important;
    }
    
    #submitBtn {
        margin-top: 25px !important;
        padding: 13px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
    }
    
    @media (max-width: 480px) {
        .recaptcha-container {
            padding: 12px 8px;
        }
        
        .recaptcha-container > div {
            transform: scale(0.88);
        }
    }
</style>
@endpush

@section('content')
<div class="logo">
    <h1>{{ config('app.brand_name') }}</h1>
</div>

<div class="welcome-message">
    <h2>{{ trans('onboarding.create_space') }}</h2>
    <p>{{ trans('onboarding.create_space_subtitle') }}</p>
</div>

@if(session('error'))
    <div class="error-message" style="background: #fee2e2; color: #991b1b; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
        {{ session('error') }}
    </div>
@endif

<form method="POST" action="{{ route('onboarding.storeInitialData') }}" id="initialForm">
    @csrf
    <input type="hidden" name="_token" value="{{ csrf_token() }}" id="csrf_token">
    
    <div class="form-group">
        <label for="email">Adresse e-mail *</label>
        <input type="email" id="email" name="email" required 
               value="{{ old('email') }}" placeholder="votre@email.com" autocomplete="email">
        @error('email')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="organization_name">Nom de votre organisation</label>
        <input type="text" id="organization_name" name="organization_name" 
               value="{{ old('organization_name') }}" placeholder="Ex: Mon Organisation">
        @error('organization_name')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    @php
        $recaptchaSiteKey = config('services.recaptcha.site_key');
        $recaptchaSecretKey = config('services.recaptcha.secret_key');
        $showRecaptcha = !empty($recaptchaSiteKey) && !empty($recaptchaSecretKey);
    @endphp

    @if($showRecaptcha)
    <div class="recaptcha-wrapper">
        <div class="recaptcha-container" id="recaptchaContainer">
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}" data-callback="recaptchaCallback"></div>
        </div>
        @error('g-recaptcha-response')
            <div class="error-message" style="text-align: center; margin-top: 10px; color: #e74c3c;">{{ $message }}</div>
        @enderror
    </div>
    @else
    <div class="recaptcha-wrapper" style="display: none;">
        <input type="hidden" name="g-recaptcha-response" value="dev-bypass">
    </div>
    @endif

    <button type="submit" class="btn btn-primary" id="submitBtn">
        Continuer
    </button>
</form>

@push('scripts')
@if($showRecaptcha)
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
    // Callback quand reCAPTCHA est complété
    function recaptchaCallback(token) {
        console.log('reCAPTCHA completed with token:', token ? 'present' : 'missing');
    }
</script>
@endif
<script>
    
        $(document).ready(function() {
            // Afficher toutes les erreurs de validation
            @if($errors->any())
                @foreach($errors->all() as $error)
                    console.error('Validation error: {{ $error }}');
                @endforeach
            @endif
            
            // Fonction pour afficher les erreurs (sans jQuery pour éviter les violations de performance)
            // Fonction pour afficher les erreurs avec SweetAlert2
            function showError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Oups...',
                    text: message,
                    confirmButtonColor: '#00286f',
                    confirmButtonText: 'D\'accord'
                });
            }
            
            const form = document.getElementById('initialForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('=== Form submit event triggered ===');
                    console.log('Form action:', form.action);
                    console.log('Form method:', form.method);
                    
                    // Désactiver le bouton immédiatement
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Traitement...';
                    }
                    
                    @if($showRecaptcha)
                    console.log('reCAPTCHA validation required');
                    
                    // Vérifier reCAPTCHA
                    if (typeof grecaptcha === 'undefined') {
                        console.error('grecaptcha is undefined');
                        e.preventDefault();
                        showError('reCAPTCHA est en cours de chargement. Veuillez patienter quelques instants et réessayer.');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Continuer';
                        }
                        return false;
                    }
                    
                    try {
                        // Obtenir la réponse reCAPTCHA (automatique avec le chargement standard)
                        const recaptchaResponse = grecaptcha.getResponse();
                        
                        console.log('reCAPTCHA response length:', recaptchaResponse ? recaptchaResponse.length : 0);
                        
                        if (!recaptchaResponse || recaptchaResponse.length === 0) {
                            console.error('reCAPTCHA response is empty');
                            e.preventDefault();
                            showError('Veuillez compléter la vérification reCAPTCHA en cochant la case "Je ne suis pas un robot".');
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Continuer';
                            }
                            return false;
                        }
                        
                        console.log('reCAPTCHA validation passed');
                    } catch(err) {
                        console.error('reCAPTCHA error:', err);
                        e.preventDefault();
                        showError('Erreur lors de la vérification reCAPTCHA. Veuillez rafraîchir la page et réessayer.');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Continuer';
                        }
                        return false;
                    }
                    @else
                    console.log('reCAPTCHA validation skipped (not configured)');
                    @endif
                    
                    // Si on arrive ici, le formulaire peut être soumis
                    console.log('Form submission allowed, submitting...');
                    console.log('Form data:', new FormData(form));
                    return true;
                });
            } else {
                console.error('Form element not found!');
            }
        });
</script>
<style>
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
@endpush
@endsection
