<div class="card">
    <div class="card-header">
        <h3 class="card-title">Actions rapides</h3>
    </div>
    @php
        // Préserver le token auto_login_token dans tous les liens
        $token = request()->has('auto_login_token') ? request()->query('auto_login_token') : null;
        $appendToken = function($url) use ($token) {
            if ($token) {
                return $url . (str_contains($url, '?') ? '&' : '?') . 'auto_login_token=' . $token;
            }
            return $url;
        };
    @endphp
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="{{ $appendToken(route('dashboard.users.create')) }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div>
                <div style="font-weight: 600; font-size: 14px;">Créer utilisateur</div>
                <div style="font-size: 12px; color: #666;">Ajouter un nouvel utilisateur</div>
            </div>
        </a>
        
        <a href="{{ $appendToken(route('dashboard.activities')) }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div>
                <div style="font-weight: 600; font-size: 14px;">Voir activités</div>
                <div style="font-size: 12px; color: #666;">Consulter l'historique</div>
            </div>
        </a>
        
        @php
            // Préserver le token auto_login_token dans les liens
            $reportsUrl = $appendToken(route('dashboard.reports'));
            $settingsUrl = $appendToken(route('dashboard.settings'));
        @endphp
        <a href="{{ $reportsUrl }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div>
                <div style="font-weight: 600; font-size: 14px;">Rapports</div>
                <div style="font-size: 12px; color: #666;">Analyses et statistiques</div>
            </div>
        </a>
        
        <a href="{{ $settingsUrl }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div>
                <div style="font-weight: 600; font-size: 14px;">Paramètres</div>
                <div style="font-size: 12px; color: #666;">Configuration système</div>
            </div>
        </a>
    </div>
</div>
