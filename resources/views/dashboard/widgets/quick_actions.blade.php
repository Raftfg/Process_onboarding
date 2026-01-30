<div class="widget widget-quick-actions widget-{{ $size ?? 'small' }}">
    <div class="details-section">
        <h3>Actions rapides</h3>
        <div style="display: grid; gap: 10px; margin-top: 15px;">
            <a href="{{ route('dashboard.config') }}" class="quick-action-btn">
                ⚙️ Configurer le dashboard
            </a>
            <a href="{{ route('dashboard') }}" class="quick-action-btn">
                📊 Voir le dashboard
            </a>
        </div>
    </div>
</div>

<style>
.quick-action-btn {
    display: block;
    padding: 12px 20px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
    font-size: 14px;
}

.quick-action-btn:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
</style>

