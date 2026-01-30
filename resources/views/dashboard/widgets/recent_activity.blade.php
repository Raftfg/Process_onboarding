<div class="widget widget-recent-activity widget-{{ $size ?? 'medium' }}">
    <div class="details-section">
        <h3>Activité récente</h3>
        <div style="margin-top: 15px;">
            <div class="activity-item">
                <div class="activity-icon">✅</div>
                <div class="activity-content">
                    <div class="activity-title">Connexion réussie</div>
                    <div class="activity-time">Il y a quelques instants</div>
                </div>
            </div>
            @if(isset($tenant))
            <div class="activity-item">
                <div class="activity-icon">🏢</div>
                <div class="activity-content">
                    <div class="activity-title">Tenant activé : {{ $tenant->name }}</div>
                    <div class="activity-time">{{ $tenant->created_at ? $tenant->created_at->diffForHumans() : 'N/A' }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
.activity-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    font-size: 24px;
    width: 40px;
    text-align: center;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.activity-time {
    font-size: 12px;
    color: #999;
}
</style>

