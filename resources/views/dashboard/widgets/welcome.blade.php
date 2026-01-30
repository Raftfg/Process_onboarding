<div class="widget widget-welcome widget-{{ $size ?? 'medium' }}">
    <div class="welcome-card">
        <h2>Bienvenue, {{ Auth::user()->name ?? 'Administrateur' }} !</h2>
        <p>Votre espace MedKey est maintenant configuré et prêt à l'emploi.</p>
        @if(isset($tenant))
            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                Connecté sur <strong>{{ $tenant->name ?? $tenant->subdomain }}</strong>
            </p>
        @endif
    </div>
</div>

