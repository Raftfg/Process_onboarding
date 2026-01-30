@extends('layouts.dashboard')

@section('title', 'Paramètres')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Paramètres</h1>
        <p style="color: #666;">Gérer vos paramètres personnels</p>
    </div>

    <div class="card">
        <form action="{{ route('dashboard.settings.update') }}" method="POST">
            @csrf
            @method('PUT')
            
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">Informations personnelles</h3>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nom complet *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('name')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('email')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div style="margin-bottom: 30px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Téléphone</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" style="width: 100%; max-width: 300px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                @error('phone')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>
            
            <h3 style="font-size: 18px; font-weight: 600; margin: 30px 0 20px 0; padding-top: 20px; border-top: 1px solid var(--border-color);">Sécurité</h3>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nouveau mot de passe</label>
                    <input type="password" name="password" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">Laisser vide pour ne pas changer</div>
                    @error('password')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirmation" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
@endsection
