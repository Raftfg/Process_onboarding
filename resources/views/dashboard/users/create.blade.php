@extends('layouts.dashboard')

@section('title', 'Créer un utilisateur')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Créer un utilisateur</h1>
        <p style="color: #666;">Ajouter un nouvel utilisateur au système</p>
    </div>

    <div class="card">
        <form action="{{ route('dashboard.users.store') }}" method="POST">
            @csrf
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nom complet *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('name')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('email')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Mot de passe *</label>
                    <input type="password" name="password" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('password')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Confirmer le mot de passe *</label>
                    <input type="password" name="password_confirmation" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Rôle *</label>
                    <select name="role" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                        <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>Utilisateur</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrateur</option>
                    </select>
                    @error('role')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('phone')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Créer l'utilisateur
                </button>
                <a href="{{ route('dashboard.users.index') }}" style="padding: 12px 24px; background: var(--bg-color); color: var(--text-color); border-radius: 8px; text-decoration: none; font-weight: 600;">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
