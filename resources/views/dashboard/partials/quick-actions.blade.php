<div class="card">
    <div class="card-header">
        <h3 class="card-title">Actions rapides</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="{{ route('dashboard.users.create') }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; font-size: 20px;">➕</div>
            <div>
                <div style="font-weight: 600; font-size: 14px;">Créer utilisateur</div>
                <div style="font-size: 12px; color: #666;">Ajouter un nouvel utilisateur</div>
            </div>
        </a>
        
        <a href="{{ route('dashboard.activities') }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; font-size: 20px;">📝</div>
            <div>
                <div style="font-weight: 600; font-size: 14px;">Voir activités</div>
                <div style="font-size: 12px; color: #666;">Consulter l'historique</div>
            </div>
        </a>
        
        <a href="{{ route('dashboard.reports') }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; font-size: 20px;">📈</div>
            <div>
                <div style="font-weight: 600; font-size: 14px;">Rapports</div>
                <div style="font-size: 12px; color: #666;">Analyses et statistiques</div>
            </div>
        </a>
        
        <a href="{{ route('dashboard.settings') }}" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--bg-color); border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s; border: 2px solid transparent;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(102, 126, 234, 0.1); display: flex; align-items: center; justify-content: center; font-size: 20px;">⚙️</div>
            <div>
                <div style="font-weight: 600; font-size: 14px;">Paramètres</div>
                <div style="font-size: 12px; color: #666;">Configuration système</div>
            </div>
        </a>
    </div>
</div>
