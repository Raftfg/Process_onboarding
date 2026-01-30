<div class="card">
    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Personnalisation visuelle</h3>
    
    <form action="{{ route('dashboard.customization.branding') }}" method="POST" style="margin-bottom: 30px;">
        @csrf
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Couleur primaire</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" name="primary_color" value="{{ $branding['primary_color'] ?? '#667eea' }}" style="width: 60px; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="text" name="primary_color" value="{{ $branding['primary_color'] ?? '#667eea' }}" pattern="^#[0-9A-Fa-f]{6}$" required style="flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Couleur secondaire</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" name="secondary_color" value="{{ $branding['secondary_color'] ?? '#764ba2' }}" style="width: 60px; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="text" name="secondary_color" value="{{ $branding['secondary_color'] ?? '#764ba2' }}" pattern="^#[0-9A-Fa-f]{6}$" required style="flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Couleur d'accent</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" name="accent_color" value="{{ $branding['accent_color'] ?? '#10b981' }}" style="width: 60px; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="text" name="accent_color" value="{{ $branding['accent_color'] ?? '#10b981' }}" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Couleur de fond</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" name="background_color" value="{{ $branding['background_color'] ?? '#f5f7fa' }}" style="width: 60px; height: 40px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">
                    <input type="text" name="background_color" value="{{ $branding['background_color'] ?? '#f5f7fa' }}" pattern="^#[0-9A-Fa-f]{6}$" style="flex: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;">
                </div>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">Nom de l'organisation</label>
            <input type="text" name="organization_name" value="{{ $branding['organization_name'] ?? '' }}" style="width: 100%; max-width: 500px; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px;" placeholder="Nom de votre organisation">
        </div>
        
        <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            Enregistrer le branding
        </button>
    </form>
    
    <div style="border-top: 1px solid var(--border-color); padding-top: 20px; margin-top: 30px;">
        <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Logo</h4>
        
        @if($branding['logo_url'] ?? null)
            @php
                // S'assurer que l'URL est absolue
                $logoUrl = $branding['logo_url'];
                if (!filter_var($logoUrl, FILTER_VALIDATE_URL)) {
                    // Si c'est un chemin relatif, le rendre absolu
                    if (strpos($logoUrl, '/storage/') === 0) {
                        $logoUrl = request()->getSchemeAndHttpHost() . $logoUrl;
                    } else {
                        $logoUrl = asset($logoUrl);
                    }
                }
            @endphp
            <div style="margin-bottom: 15px;">
                <img src="{{ $logoUrl }}" alt="Logo" style="max-width: 200px; max-height: 100px; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;" onerror="this.style.display='none';">
            </div>
        @endif
        
        <form action="{{ route('dashboard.customization.logo') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="file" name="logo" accept="image/*" required style="flex: 1; min-width: 200px; padding: 8px; border: 1px solid var(--border-color); border-radius: 8px;">
                <button type="submit" style="background: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                    Uploader le logo
                </button>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Formats acceptés: JPEG, PNG, GIF, SVG (max 2MB)</div>
        </form>
    </div>
</div>
