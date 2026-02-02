<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Utilisateurs totaux</div>
        </div>
        <div class="stat-card-value">{{ $stats['total_users'] ?? 0 }}</div>
        <div class="stat-card-label">{{ $stats['active_users'] ?? 0 }} actifs</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Activités aujourd'hui</div>
        </div>
        <div class="stat-card-value">{{ $stats['today_activities'] ?? 0 }}</div>
        <div class="stat-card-label">{{ $stats['recent_activities'] ?? 0 }} récentes</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Notifications</div>
        </div>
        <div class="stat-card-value">{{ $stats['unread_notifications'] ?? 0 }}</div>
        <div class="stat-card-label">Non lues</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">Statut système</div>
        </div>
        <div class="stat-card-value" style="font-size: 18px; color: #10b981;">Opérationnel</div>
        <div class="stat-card-label">Tous les services actifs</div>
    </div>
</div>
