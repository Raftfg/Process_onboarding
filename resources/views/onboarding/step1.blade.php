@extends('layouts.app')

@section('title', 'Informations de l\'hôpital - MedKey')

@section('content')
<div class="logo">
    <h1>MedKey</h1>
</div>

<div class="step-indicator">
    <div class="step active">1</div>
    <div class="step-line"></div>
    <div class="step inactive">2</div>
    <div class="step-line"></div>
    <div class="step inactive">3</div>
</div>

<h2 style="text-align: center; margin-bottom: 30px; color: #333;">Informations de l'hôpital</h2>

<form id="step1Form" method="POST" action="{{ route('onboarding.storeStep1') }}">
    @csrf
    
    <div class="form-group">
        <label for="hospital_name">Nom de l'hôpital *</label>
        <input type="text" id="hospital_name" name="hospital_name" required 
               value="{{ old('hospital_name') }}" placeholder="Ex: Hôpital Central">
        @error('hospital_name')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="hospital_address">Adresse</label>
        <textarea id="hospital_address" name="hospital_address" 
                  placeholder="Adresse complète de l'hôpital">{{ old('hospital_address') }}</textarea>
        @error('hospital_address')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="hospital_phone">Téléphone</label>
        <input type="tel" id="hospital_phone" name="hospital_phone" 
               value="{{ old('hospital_phone') }}" placeholder="+33 1 23 45 67 89">
        @error('hospital_phone')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="hospital_email">Email de l'hôpital</label>
        <input type="email" id="hospital_email" name="hospital_email" 
               value="{{ old('hospital_email') }}" placeholder="contact@hopital.fr">
        @error('hospital_email')
            <div class="error-message">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">Continuer</button>
</form>
@endsection
