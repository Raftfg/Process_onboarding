@extends('layouts.app')

@section('title', 'Activer votre compte - Akasi Group')

@push('styles')
<style>
    .activation-container {
        max-width: 500px;
        margin: 0 auto;
    }
    
    .email-display {
        background: #f0f7ff;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 25px;
        border-left: 4px solid #00286f;
        text-align: center;
    }
    
    .email-display strong {
        color: #00286f;
        font-size: 16px;
    }
</style>
@endpush

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="welcome-message">
    <h2>Finaliser votre inscription</h2>
    <p>Définissez votre mot de passe pour activer votre compte</p>
    @if(isset($organizationName))
        <p style="margin-top: 10px; color: #666;">Pour l'organisation : <strong>{{ $organizationName }}</strong></p>
    @endif
</div>

@if(session('error') || $errors->has('activation'))
    <div class="error-message" style="background: #fee; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #e74c3c;">
        {{ session('error') ?? $errors->first('activation') }}
    </div>
@endif

<div class="email-display">
    <strong>{{ $email }}</strong>
</div>

<form method="POST" action="{{ route('onboarding.activate') }}" id="activationForm">
    @csrf
    
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="hidden" name="email" value="{{ $email }}">
    
    <div class="form-group">
        <label for="password">Mot de passe *</label>
        <input type="password" id="password" name="password" required 
               placeholder="Minimum 8 caractères" autocomplete="new-password" minlength="8">
        @error('password')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="password_confirmation">Confirmer le mot de passe *</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required 
               placeholder="Répétez votre mot de passe" autocomplete="new-password" minlength="8">
    </div>

    <button type="submit" class="btn btn-primary" id="submitBtn">
Continue
    </button>
</form>

@push('scripts')
<script>
    $(document).ready(function() {
        // Fonction pour afficher les erreurs
        // Fonction pour afficher les erreurs avec SweetAlert2
        function showError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: message,
                confirmButtonColor: '#00286f',
                confirmButtonText: 'Fermer'
            });
        }
        
        $('#activationForm').on('submit', function(e) {
            const password = $('#password').val();
            const passwordConfirmation = $('#password_confirmation').val();
            
            // Supprimer les erreurs précédentes
            $('.form-error').remove();
            $('.form-group').removeClass('has-error');
            
            let hasError = false;
            
            if (password.length < 8) {
                e.preventDefault();
                showError('Le mot de passe doit contenir au moins 8 caractères');
                $('#password').closest('.form-group').addClass('has-error');
                hasError = true;
            }
            
            if (password !== passwordConfirmation) {
                e.preventDefault();
                showError('Les mots de passe ne correspondent pas');
                $('#password').closest('.form-group').addClass('has-error');
                $('#password_confirmation').closest('.form-group').addClass('has-error');
                hasError = true;
            }
            
            if (hasError) {
                return false;
            }
            
            // Désactiver le bouton et afficher un message de chargement
            $('#submitBtn').prop('disabled', true).text('Activation en cours...');
        });
        
        // Réactiver le bouton si l'utilisateur modifie les champs
        $('#password, #password_confirmation').on('input', function() {
            $('#submitBtn').prop('disabled', false).text('Continue');
            $('.form-error').fadeOut(300, function() {
                $(this).remove();
            });
            $(this).closest('.form-group').removeClass('has-error');
        });
    });
</script>
<style>
    .form-group.has-error input {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }
</style>
@endpush
@endsection
