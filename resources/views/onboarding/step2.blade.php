@extends('layouts.app')

@section('title', 'Informations administrateur - MedKey')

@section('content')
<div class="logo">
    <h1>MedKey</h1>
</div>

<div class="step-indicator">
    <div class="step active">1</div>
    <div class="step-line completed"></div>
    <div class="step active">2</div>
    <div class="step-line"></div>
    <div class="step inactive">3</div>
</div>

<h2 style="text-align: center; margin-bottom: 30px; color: #333;">Informations administrateur</h2>

<form id="step2Form" method="POST">
    @csrf
    
    <div class="form-group">
        <label for="admin_first_name">Prénom *</label>
        <input type="text" id="admin_first_name" name="admin_first_name" required 
               value="{{ old('admin_first_name') }}" placeholder="Jean">
        <div class="error-message" id="error_admin_first_name"></div>
    </div>

    <div class="form-group">
        <label for="admin_last_name">Nom *</label>
        <input type="text" id="admin_last_name" name="admin_last_name" required 
               value="{{ old('admin_last_name') }}" placeholder="Dupont">
        <div class="error-message" id="error_admin_last_name"></div>
    </div>

    <div class="form-group">
        <label for="admin_email">Email administrateur *</label>
        <input type="email" id="admin_email" name="admin_email" required 
               value="{{ old('admin_email') }}" placeholder="admin@hopital.fr">
        <div class="error-message" id="error_admin_email"></div>
    </div>

    <div class="form-group">
        <label for="admin_password">Mot de passe *</label>
        <input type="password" id="admin_password" name="admin_password" required 
               placeholder="Minimum 8 caractères" minlength="8">
        <div class="error-message" id="error_admin_password"></div>
    </div>

    <div class="form-group">
        <label for="admin_password_confirmation">Confirmer le mot de passe *</label>
        <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required 
               placeholder="Répétez le mot de passe">
        <div class="error-message" id="error_admin_password_confirmation"></div>
    </div>

    <div class="form-group">
        <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}"></div>
        <div class="error-message" id="error_recaptcha"></div>
    </div>

    <button type="submit" class="btn btn-primary" id="submitBtn">Finaliser</button>
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#step2Form').on('submit', function(e) {
        e.preventDefault();
        
        // Désactiver le bouton
        $('#submitBtn').prop('disabled', true).text('Traitement...');
        
        // Afficher le loading
        $('#loadingOverlay').addClass('active');
        
        // Récupérer le token CSRF
        const token = $('meta[name="csrf-token"]').attr('content');
        
        // Récupérer le token reCAPTCHA
        const recaptchaToken = grecaptcha.getResponse();
        if (!recaptchaToken) {
            $('#error_recaptcha').text('Veuillez compléter la vérification reCAPTCHA.');
            $('#loadingOverlay').removeClass('active');
            $('#submitBtn').prop('disabled', false).text('Finaliser');
            return;
        }
        
        // Envoyer les données avec le token reCAPTCHA
        const formData = $(this).serialize() + '&g-recaptcha-response=' + encodeURIComponent(recaptchaToken);
        
        $.ajax({
            url: '{{ route("onboarding.storeStep2") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token
            },
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Démarrer le processus d'onboarding
                    startOnboardingProcess(response.session_id);
                }
            },
            error: function(xhr) {
                $('#loadingOverlay').removeClass('active');
                $('#submitBtn').prop('disabled', false).text('Finaliser');
                
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    // Afficher les erreurs
                    Object.keys(errors).forEach(function(key) {
                        if (key === 'recaptcha') {
                            $('#error_recaptcha').text(errors[key][0]);
                        } else {
                            $('#error_' + key).text(errors[key][0]);
                        }
                    });
                    // Réinitialiser reCAPTCHA
                    grecaptcha.reset();
                } else {
                    alert('Une erreur est survenue. Veuillez réessayer.');
                    grecaptcha.reset();
                }
            }
        });
    });
    
    function startOnboardingProcess(sessionId) {
        // Appeler l'API pour démarrer le processus
        $.ajax({
            url: '/api/onboarding/complete',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Si le résultat est déjà disponible, rediriger directement vers la page de connexion
                    if (response.result && response.result.url) {
                        console.log('Redirection vers:', response.result.url);
                        $('#loadingOverlay .loading-text').text('Redirection vers la page de connexion...');
                        // Utiliser replace pour éviter que l'utilisateur puisse revenir en arrière
                        setTimeout(function() {
                            // Rediriger directement vers la page de connexion
                            window.location.replace(response.result.url);
                        }, 1500);
                    } else {
                        // Sinon, faire du polling
                        checkOnboardingStatus(sessionId);
                    }
                } else {
                    console.error('Erreur onboarding:', response.message);
                    $('#loadingOverlay').removeClass('active');
                    alert('Erreur: ' + (response.message || 'Une erreur est survenue'));
                    $('#submitBtn').prop('disabled', false).text('Finaliser');
                }
            },
            error: function() {
                $('#loadingOverlay').removeClass('active');
                alert('Erreur lors du démarrage du processus. Veuillez réessayer.');
                $('#submitBtn').prop('disabled', false).text('Finaliser');
            }
        });
    }
    
    function checkOnboardingStatus(sessionId) {
        const checkInterval = setInterval(function() {
            $.ajax({
                url: '/api/onboarding/status/' + sessionId,
                method: 'GET',
                success: function(response) {
                    if (response.status === 'completed') {
                        clearInterval(checkInterval);
                        // Rediriger vers la page de connexion
                        if (response.result && response.result.url) {
                            console.log('Redirection vers (polling):', response.result.url);
                            setTimeout(function() {
                                // Rediriger directement vers la page de connexion
                                window.location.replace(response.result.url);
                            }, 1000);
                        } else {
                            $('#loadingOverlay').removeClass('active');
                            alert('Onboarding terminé avec succès ! Veuillez vous connecter.');
                        }
                    } else if (response.status === 'failed') {
                        clearInterval(checkInterval);
                        $('#loadingOverlay').removeClass('active');
                        alert('Erreur: ' + (response.error || 'Une erreur est survenue'));
                        $('#submitBtn').prop('disabled', false).text('Finaliser');
                    }
                },
                error: function() {
                    clearInterval(checkInterval);
                    $('#loadingOverlay').removeClass('active');
                    alert('Erreur lors de la vérification du statut.');
                    $('#submitBtn').prop('disabled', false).text('Finaliser');
                }
            });
        }, 2000); // Vérifier toutes les 2 secondes
    }
});
</script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush
