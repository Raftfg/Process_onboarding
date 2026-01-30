@if(isset($tenant))
<div class="widget widget-stats widget-{{ $size ?? 'medium' }}">
    <div class="info-grid">
        <div class="info-card">
            <h3>Statut</h3>
            <div class="value">
                <span class="status-badge completed">{{ ucfirst($tenant->status) }}</span>
            </div>
            <div class="label">Système opérationnel</div>
        </div>

        <div class="info-card">
            <h3>Sous-domaine</h3>
            <div class="value">{{ $tenant->subdomain ?? 'N/A' }}</div>
            <div class="label">Identifiant unique</div>
        </div>

        <div class="info-card">
            <div class="value">{{ $tenant->created_at ? $tenant->created_at->format('d/m/Y') : 'N/A' }}</div>
            <div class="label">Date d'activation</div>
        </div>
    </div>
</div>
@endif

