@extends('layouts.dashboard')

@section('title', 'Personnalisation')

@section('content')
    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 600;">Personnalisation</h1>
        <p style="color: #666;">Personnalisez l'apparence et le comportement de votre espace</p>
    </div>

    @if(session('success'))
        <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6ee7b7;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fca5a5;">
            {{ session('error') }}
        </div>
    @endif

    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); overflow-x: auto;">
        <button onclick="showTab('branding')" id="tab-branding" class="tab-button active" style="padding: 12px 20px; border: none; background: transparent; border-bottom: 3px solid var(--primary-color); cursor: pointer; font-weight: 600; color: var(--primary-color); white-space: nowrap; flex-shrink: 0;">
            Branding
        </button>
        <button onclick="showTab('layout')" id="tab-layout" class="tab-button" style="padding: 12px 20px; border: none; background: transparent; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #666; white-space: nowrap; flex-shrink: 0;">
            Layout
        </button>
        <button onclick="showTab('menu')" id="tab-menu" class="tab-button" style="padding: 12px 20px; border: none; background: transparent; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #666; white-space: nowrap; flex-shrink: 0;">
            Menu
        </button>
    </div>

    <!-- Onglet Branding -->
    <div id="content-branding" class="tab-content">
        @include('dashboard.customization.partials.branding')
    </div>

    <!-- Onglet Layout -->
    <div id="content-layout" class="tab-content" style="display: none;">
        @include('dashboard.customization.partials.layout')
    </div>

    <!-- Onglet Menu -->
    <div id="content-menu" class="tab-content" style="display: none;">
        @include('dashboard.customization.partials.menu')
    </div>

    <script>
        function showTab(tabName) {
            // Masquer tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Désactiver tous les boutons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
                button.style.borderBottomColor = 'transparent';
                button.style.color = '#666';
            });
            
            // Afficher le contenu sélectionné
            document.getElementById('content-' + tabName).style.display = 'block';
            
            // Activer le bouton sélectionné
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('active');
            activeButton.style.borderBottomColor = 'var(--primary-color)';
            activeButton.style.color = 'var(--primary-color)';
        }
        
        // Mettre à jour les variables CSS après modification du branding
        @if(session('success'))
            // Forcer le rechargement complet de la page après 500ms pour appliquer les nouvelles couleurs
            setTimeout(function() {
                window.location.reload(true);
            }, 500);
        @endif
    </script>
@endsection
