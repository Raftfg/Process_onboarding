@extends('layouts.app')

@section('title', 'Espace créé - Akasi Group')

@push('styles')
<style>
    .confirmation-container {
        text-align: center;
        padding: 40px 20px;
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 30px;
        background: #4caf50;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: scaleIn 0.5s ease-out;
    }
    
    .success-icon::after {
        content: '✓';
        color: white;
        font-size: 60px;
        font-weight: bold;
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .confirmation-title {
        font-size: 2rem;
        color: #333;
        margin-bottom: 15px;
        animation: fadeInUp 0.6s ease-out 0.2s both;
    }
    
    .confirmation-message {
        font-size: 16px;
        color: #666;
        line-height: 1.6;
        margin-bottom: 30px;
        animation: fadeInUp 0.6s ease-out 0.4s both;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .email-highlight {
        background: #f0f7ff;
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
        border-left: 4px solid #00286f;
    }
    
    .email-highlight strong {
        color: #00286f;
    }
</style>
@endpush

@section('content')
<div class="logo">
    <h1>Akasi Group</h1>
</div>

<div class="confirmation-container">
    <div class="success-icon"></div>
    
    <h2 class="confirmation-title">Félicitations !</h2>
    
    <div class="confirmation-message">
        <p>Votre espace <strong>Akasi Group</strong> a été créé avec succès !</p>
        
        @if(isset($organization_name))
            <p style="margin-top: 15px;">Organisation : <strong>{{ $organization_name }}</strong></p>
        @endif
    </div>
    
    <div class="email-highlight">
        <p style="margin: 0;">
            <strong>📧 Consultez votre boîte e-mail</strong><br>
            @if(isset($email))
                <span style="color: #00286f;">{{ $email }}</span>
            @else
                Un lien d'activation vous a été envoyé par e-mail.
            @endif
        </p>
        <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
            Cliquez sur le lien d'activation pour finaliser votre inscription et définir votre mot de passe.
        </p>
    </div>
    
    <p style="margin-top: 30px; font-size: 14px; color: #999;">
        Vous n'avez pas reçu l'email ? Vérifiez votre dossier spam ou contactez le support.
    </p>
</div>
@endsection
