@extends('layouts.dashboard')

@section('title', 'Paramètres')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Paramètres</h1>
        <p style="color: #666;">Gérer vos paramètres personnels</p>
    </div>

    @php
        $user = Auth::user();
        // Vérifier si le profil est incomplet (pour afficher une notification)
        $isProfileIncomplete = empty($user->first_name) || empty($user->last_name) || empty($user->phone) || empty($user->company);
    @endphp

    @if($isProfileIncomplete)
    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin-bottom: 24px; border-radius: 8px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="font-size: 24px;">⚠️</div>
            <div style="flex: 1;">
                <strong style="color: #92400e; display: block; margin-bottom: 4px;">Profil incomplet</strong>
                <p style="color: #78350f; margin: 0; font-size: 14px;">Veuillez compléter votre profil pour une meilleure expérience. Certaines informations sont manquantes.</p>
            </div>
        </div>
    </div>
    @endif

    @if(session('success'))
    <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 16px; margin-bottom: 24px; border-radius: 8px; color: #065f46;">
        {{ session('success') }}
    </div>
    @endif

    <div class="card">
        @php
            // Préserver le token auto_login_token dans l'URL du formulaire
            $formAction = route('dashboard.settings.update');
            if (request()->has('auto_login_token')) {
                $token = request()->query('auto_login_token');
                $formAction .= (str_contains($formAction, '?') ? '&' : '?') . 'auto_login_token=' . $token;
            }
        @endphp
        <form action="{{ $formAction }}" method="POST">
            @csrf
            @method('PUT')
            
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">Informations personnelles</h3>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Prénom</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $user?->first_name ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('first_name')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nom</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $user?->last_name ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('last_name')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nom complet *</label>
                <input type="text" name="name" value="{{ old('name', $user?->name ?? '') }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Ce champ sera automatiquement rempli avec "Prénom Nom" si vous remplissez les champs ci-dessus</div>
                @error('name')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $user?->email ?? '') }}" required style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('email')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $user?->phone ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('phone')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Poste / Fonction</label>
                <input type="text" name="job_title" value="{{ old('job_title', $user?->job_title ?? '') }}" placeholder="Ex: Directeur, Manager, etc." style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                @error('job_title')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Biographie</label>
                <textarea name="bio" rows="3" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; resize: vertical;">{{ old('bio', $user?->bio ?? '') }}</textarea>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Une courte description de vous-même</div>
                @error('bio')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>
            
            <h3 style="font-size: 18px; font-weight: 600; margin: 30px 0 20px 0; padding-top: 20px; border-top: 1px solid var(--border-color);">Informations professionnelles</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Entreprise / Organisation</label>
                <input type="text" name="company" value="{{ old('company', $user?->company ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                @error('company')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>
            
            <h3 style="font-size: 18px; font-weight: 600; margin: 30px 0 20px 0; padding-top: 20px; border-top: 1px solid var(--border-color);">Adresse</h3>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Adresse</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; resize: vertical;">{{ old('address', $user?->address ?? '') }}</textarea>
                @error('address')
                    <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Ville</label>
                    <input type="text" name="city" value="{{ old('city', $user?->city ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('city')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Code postal</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $user?->postal_code ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('postal_code')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Pays</label>
                    <input type="text" name="country" value="{{ old('country', $user?->country ?? '') }}" style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px;">
                    @error('country')
                        <div style="color: #ef4444; font-size: 14px; margin-top: 5px;">{{ $message }}</div>
                    @enderror
                </div>
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

    <script>
        // Auto-remplir le nom complet si prénom et nom sont remplis
        document.addEventListener('DOMContentLoaded', function() {
            const firstNameInput = document.querySelector('input[name="first_name"]');
            const lastNameInput = document.querySelector('input[name="last_name"]');
            const nameInput = document.querySelector('input[name="name"]');
            
            function updateFullName() {
                const firstName = firstNameInput.value.trim();
                const lastName = lastNameInput.value.trim();
                
                if (firstName && lastName) {
                    nameInput.value = firstName + ' ' + lastName;
                } else if (firstName) {
                    nameInput.value = firstName;
                } else if (lastName) {
                    nameInput.value = lastName;
                }
            }
            
            if (firstNameInput && lastNameInput && nameInput) {
                firstNameInput.addEventListener('input', updateFullName);
                lastNameInput.addEventListener('input', updateFullName);
            }
        });
    </script>
@endsection
