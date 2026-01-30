<div class="card">
    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Configuration du layout</h3>
    
    <form action="{{ route('dashboard.customization.layout') }}" method="POST">
        @csrf
        
        <div style="margin-bottom: 30px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Message de bienvenue</label>
            <textarea name="welcome_message" rows="3" style="width: 100%; max-width: 600px; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;" placeholder="Bienvenue sur votre espace">{{ $layout['welcome_message'] ?? 'Bienvenue sur votre espace' }}</textarea>
        </div>
        
        <div style="margin-bottom: 30px;">
            <label style="display: block; margin-bottom: 15px; font-weight: 600;">Widgets du dashboard</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" name="dashboard_widgets[stats]" value="1" {{ ($layout['dashboard_widgets']['stats'] ?? true) ? 'checked' : '' }}>
                    <span>📊 Statistiques</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" name="dashboard_widgets[activities]" value="1" {{ ($layout['dashboard_widgets']['activities'] ?? true) ? 'checked' : '' }}>
                    <span>📝 Activités récentes</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" name="dashboard_widgets[calendar]" value="1" {{ ($layout['dashboard_widgets']['calendar'] ?? true) ? 'checked' : '' }}>
                    <span>📅 Calendrier</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="checkbox" name="dashboard_widgets[quick_actions]" value="1" {{ ($layout['dashboard_widgets']['quick_actions'] ?? true) ? 'checked' : '' }}>
                    <span>⚡ Actions rapides</span>
                </label>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nombre de colonnes</label>
                <select name="grid_columns" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <option value="2" {{ ($layout['grid_columns'] ?? 3) == 2 ? 'selected' : '' }}>2 colonnes</option>
                    <option value="3" {{ ($layout['grid_columns'] ?? 3) == 3 ? 'selected' : '' }}>3 colonnes</option>
                    <option value="4" {{ ($layout['grid_columns'] ?? 3) == 4 ? 'selected' : '' }}>4 colonnes</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Espacement</label>
                <select name="spacing" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                    <option value="compact" {{ ($layout['spacing'] ?? 'normal') == 'compact' ? 'selected' : '' }}>Compact</option>
                    <option value="normal" {{ ($layout['spacing'] ?? 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="comfortable" {{ ($layout['spacing'] ?? 'normal') == 'comfortable' ? 'selected' : '' }}>Confortable</option>
                </select>
            </div>
        </div>
        
        <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            Enregistrer le layout
        </button>
    </form>
</div>
