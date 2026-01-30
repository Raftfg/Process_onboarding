<div class="widget widget-user-info widget-{{ $size ?? 'medium' }}">
    <div class="details-section">
        <h3>Informations utilisateur connecté</h3>
        
        <div class="detail-row">
            <div class="detail-label">Nom complet</div>
            <div class="detail-value">{{ Auth::user()->name ?? 'N/A' }}</div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value">{{ Auth::user()->email ?? 'N/A' }}</div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Rôle</div>
            <div class="detail-value">{{ ucfirst(Auth::user()->role ?? 'user') }}</div>
        </div>
    </div>
</div>

