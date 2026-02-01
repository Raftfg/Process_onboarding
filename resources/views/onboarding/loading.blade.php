@extends('layouts.app')

@section('title', 'Création en cours - Akasi Group')

@push('styles')
<style>
    .loading-container {
        text-align: center;
        padding: 40px 20px;
    }
    
    .loading-spinner-large {
        width: 80px;
        height: 80px;
        border: 6px solid rgba(0, 40, 111, 0.1);
        border-top-color: #00286f;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 30px;
    }
    
    .loading-message {
        font-size: 18px;
        color: #333;
        margin-bottom: 15px;
        min-height: 30px;
        transition: opacity 0.3s ease;
    }
    
    .loading-message.active {
        opacity: 1;
    }
    
    .loading-message.inactive {
        opacity: 0.3;
    }
    
    .progress-bar {
        width: 100%;
        height: 4px;
        background: #e0e0e0;
        border-radius: 2px;
        margin-top: 30px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: #00286f;
        width: 0%;
        transition: width 0.5s ease;
        border-radius: 2px;
    }
</style>
@endpush

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="loading-container">
    <div class="loading-spinner-large"></div>
    
    <div id="messageContainer">
        <div class="loading-message active" id="message1">Veillez patienter…</div>
        <div class="loading-message inactive" id="message2">Création de votre espace Akasi Group en cours…</div>
        <div class="loading-message inactive" id="message3">Configuration de votre environnement…</div>
        <div class="loading-message inactive" id="message4">Finalisation…</div>
    </div>
    
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let currentMessage = 1;
        const totalMessages = 4;
        let progress = 0;
        
        // Afficher les messages séquentiellement
        function showNextMessage() {
            if (currentMessage < totalMessages) {
                $(`#message${currentMessage}`).removeClass('active').addClass('inactive');
                currentMessage++;
                $(`#message${currentMessage}`).removeClass('inactive').addClass('active');
                
                // Mettre à jour la barre de progression
                progress = (currentMessage / totalMessages) * 100;
                $('#progressFill').css('width', progress + '%');
            }
        }
        
        // Changer de message toutes les 2 secondes
        const messageInterval = setInterval(showNextMessage, 2000);
        
        // Appeler l'API pour traiter l'onboarding
        const onboardingData = @json(session('onboarding_data'));
        
        if (!onboardingData || !onboardingData.email || !onboardingData.organization_name) {
            window.location.href = '{{ route("onboarding.start") }}';
            return;
        }
        
        $.ajax({
            url: '{{ route("api.onboarding.process") }}',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: JSON.stringify({
                email: onboardingData.email,
                organization_name: onboardingData.organization_name
            }),
            success: function(response) {
                clearInterval(messageInterval);
                $('#message4').removeClass('inactive').addClass('active');
                $('#progressFill').css('width', '100%');
                
                // Rediriger vers la page de confirmation après un court délai
                setTimeout(function() {
                    window.location.href = '{{ route("onboarding.confirmation") }}';
                }, 1500);
            },
            error: function(xhr) {
                clearInterval(messageInterval);
                let errorMessage = 'Une erreur est survenue lors de la création de votre espace.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: errorMessage,
                    confirmButtonText: 'Réessayer',
                    confirmButtonColor: '#00286f',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = '{{ route("onboarding.start") }}';
                });
            }
        });
    });
</script>
@endpush
@endsection
