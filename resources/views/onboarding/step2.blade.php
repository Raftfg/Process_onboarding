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
        
        // Envoyer les données
        $.ajax({
            url: '{{ route("onboarding.storeStep2") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token
            },
            data: $(this).serialize(),
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
                        $('#error_' + key).text(errors[key][0]);
                    });
                } else {
                    alert('Une erreur est survenue. Veuillez réessayer.');
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
                    // Si le résultat est déjà disponible, rediriger directement
                    if (response.result && response.result.url) {
                        $('#loadingOverlay .loading-text').text('Redirection en cours...');
                        setTimeout(function() {
                            window.location.href = response.result.url + '?welcome=1';
                        }, 2000);
                    } else {
                        // Sinon, faire du polling
                        checkOnboardingStatus(sessionId);
                    }
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
                        // Rediriger vers le sous-domaine
                        if (response.result && response.result.url) {
                            setTimeout(function() {
                                window.location.href = response.result.url + '?welcome=1';
                            }, 1000);
                        } else {
                            $('#loadingOverlay').removeClass('active');
                            alert('Onboarding terminé avec succès !');
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
@endpush
