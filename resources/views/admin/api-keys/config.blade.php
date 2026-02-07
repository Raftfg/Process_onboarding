@extends('admin.layouts.app')

@section('title', 'Configuration API - ' . $key->name)

@section('content')
    <div class="card">
        <h3 style="margin-bottom: 20px;">Configuration de l'API : {{ $key->name }}</h3>
        
        @if(session('success'))
            <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.api-keys.config.update', $key->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div style="margin-bottom: 25px;">
                <h4 style="margin-bottom: 15px; color: #4a5568;">Validation des Champs</h4>
                
                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <input type="checkbox" name="require_organization_name" value="1" 
                           {{ ($config['require_organization_name'] ?? true) ? 'checked' : '' }}
                           style="width: 18px; height: 18px;">
                    <span><strong>Require organization_name</strong> - Le champ organization_name sera obligatoire dans les requêtes API</span>
                </label>
                <p style="color: #718096; font-size: 14px; margin-left: 28px; margin-top: 5px;">
                    Si désactivé, organization_name devient optionnel et sera généré automatiquement s'il n'est pas fourni.
                </p>
            </div>

            <div style="margin-bottom: 25px;">
                <h4 style="margin-bottom: 15px; color: #4a5568;">Génération Automatique du Nom d'Organisation</h4>
                
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Stratégie de génération</label>
                <select name="organization_name_generation_strategy" id="strategy" 
                        style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 15px;">
                    <option value="auto" {{ ($config['organization_name_generation_strategy'] ?? 'auto') === 'auto' ? 'selected' : '' }}>
                        Auto - Essaie metadata, puis email, puis timestamp
                    </option>
                    <option value="email" {{ ($config['organization_name_generation_strategy'] ?? '') === 'email' ? 'selected' : '' }}>
                        Email - Génère depuis l'email (ex: "User-admin" depuis "admin@example.com")
                    </option>
                    <option value="timestamp" {{ ($config['organization_name_generation_strategy'] ?? '') === 'timestamp' ? 'selected' : '' }}>
                        Timestamp - Génère depuis timestamp (ex: "Tenant-1738501234")
                    </option>
                    <option value="metadata" {{ ($config['organization_name_generation_strategy'] ?? '') === 'metadata' ? 'selected' : '' }}>
                        Metadata - Utilise un champ du metadata (name, organization_name, etc.)
                    </option>
                    <option value="custom" {{ ($config['organization_name_generation_strategy'] ?? '') === 'custom' ? 'selected' : '' }}>
                        Custom - Utilise un template personnalisé
                    </option>
                </select>

                <div id="template-section" style="display: none; margin-top: 15px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">Template personnalisé</label>
                    <input type="text" name="organization_name_template" 
                           value="{{ $config['organization_name_template'] ?? '' }}"
                           placeholder="Ex: Tenant-{timestamp} ou User-{email}"
                           style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <p style="color: #718096; font-size: 13px; margin-top: 8px;">
                        Placeholders disponibles : <code>{timestamp}</code>, <code>{email}</code>, <code>{date}</code>, <code>{datetime}</code>, <code>{random}</code>
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 30px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                    Enregistrer la configuration
                </button>
                <a href="{{ route('admin.api-keys.index') }}" class="btn" style="padding: 12px 24px; background: #e2e8f0; text-decoration: none; color: inherit;">
                    Annuler
                </a>
            </div>
        </form>
    </div>

    <script>
        // Afficher/masquer le champ template selon la stratégie
        const strategySelect = document.getElementById('strategy');
        const templateSection = document.getElementById('template-section');

        function toggleTemplate() {
            if (strategySelect.value === 'custom') {
                templateSection.style.display = 'block';
            } else {
                templateSection.style.display = 'none';
            }
        }

        strategySelect.addEventListener('change', toggleTemplate);
        toggleTemplate(); // Initialiser au chargement
    </script>
@endsection
