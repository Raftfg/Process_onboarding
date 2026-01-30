<div class="card">
    <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Configuration du menu</h3>
    <p style="color: #666; margin-bottom: 20px;">Réorganisez et personnalisez les éléments du menu sidebar</p>
    
    <form action="{{ route('dashboard.customization.menu') }}" method="POST" id="menu-form">
        @csrf
        
        <div id="menu-items" style="margin-bottom: 20px;">
            @foreach($menu['items'] ?? [] as $index => $item)
                <div class="menu-item" style="display: flex; gap: 10px; align-items: center; padding: 15px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 10px; background: #f9fafb;">
                    <div style="cursor: move; font-size: 20px;">☰</div>
                    
                    <input type="hidden" name="items[{{ $index }}][key]" value="{{ $item['key'] }}">
                    
                    <div style="flex: 1;">
                        <input type="text" name="items[{{ $index }}][label]" value="{{ $item['label'] }}" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 5px;" placeholder="Label">
                        <input type="text" name="items[{{ $index }}][icon]" value="{{ $item['icon'] ?? '' }}" style="width: 100px; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px;" placeholder="Icône (emoji)">
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 5px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 5px; font-size: 12px;">
                            <input type="checkbox" name="items[{{ $index }}][enabled]" value="1" {{ ($item['enabled'] ?? true) ? 'checked' : '' }}>
                            Actif
                        </label>
                        <input type="number" name="items[{{ $index }}][order]" value="{{ $item['order'] ?? $index + 1 }}" min="1" required style="width: 60px; padding: 5px; border: 1px solid var(--border-color); border-radius: 6px; text-align: center;" placeholder="Ordre">
                    </div>
                </div>
            @endforeach
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" style="background: var(--primary-color); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                Enregistrer le menu
            </button>
            
            <form action="{{ route('dashboard.customization.reset') }}" method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser toutes les personnalisations ?');">
                @csrf
                <button type="submit" style="background: #ef4444; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Réinitialiser
                </button>
            </form>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    // Activer le drag & drop pour réorganiser les éléments du menu
    const menuItems = document.getElementById('menu-items');
    if (menuItems) {
        new Sortable(menuItems, {
            animation: 150,
            handle: '.menu-item',
            onEnd: function(evt) {
                // Mettre à jour les ordres après le drag & drop
                const items = menuItems.querySelectorAll('.menu-item');
                items.forEach((item, index) => {
                    const orderInput = item.querySelector('input[name*="[order]"]');
                    if (orderInput) {
                        orderInput.value = index + 1;
                    }
                });
            }
        });
    }
</script>
