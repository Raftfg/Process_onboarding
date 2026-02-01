<div class="header">
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>
    <div class="header-left">
        <div class="search-box">
            <input type="text" id="global-search" placeholder="Rechercher..." autocomplete="off">
            <i>🔍</i>
        </div>
    </div>
    
    <div class="header-right">
        <div class="notification-icon" onclick="toggleNotifications()">
            <i style="font-size: 20px;">🔔</i>
            @if(isset($unreadCount) && $unreadCount > 0)
                <span class="notification-badge">{{ $unreadCount }}</span>
            @endif
        </div>
        
        <div class="profile-dropdown">
            <div class="profile-button" onclick="toggleProfileMenu()">
                <div class="user-avatar" style="width: 35px; height: 35px; background: var(--primary-color);">
                    {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->email ?? 'A', 0, 1)) }}
                </div>
                <span>{{ Auth::user()->name ?? 'Utilisateur' }}</span>
                <i>▼</i>
            </div>
            
            @php
                // Préserver le token auto_login_token dans le lien
                $settingsUrl = route('dashboard.settings');
                if (request()->has('auto_login_token')) {
                    $token = request()->query('auto_login_token');
                    $settingsUrl .= (str_contains($settingsUrl, '?') ? '&' : '?') . 'auto_login_token=' . $token;
                }
            @endphp
            <div id="profile-menu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 10px; background: white; border-radius: 8px; box-shadow: var(--shadow-lg); min-width: 200px; z-index: 1000;">
                <a href="{{ $settingsUrl }}" style="display: block; padding: 12px 20px; color: var(--text-color); text-decoration: none; border-bottom: 1px solid var(--border-color);">
                    ⚙️ Paramètres
                </a>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" style="width: 100%; text-align: left; padding: 12px 20px; background: none; border: none; color: #ef4444; cursor: pointer;">
                        🚪 Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="notifications-dropdown" style="display: none; position: fixed; top: 70px; right: 20px; width: 350px; max-height: 400px; overflow-y: auto; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1000;">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 16px; font-weight: 600;">Notifications</h3>
        <button onclick="markAllAsRead()" style="background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 12px;">
            Tout marquer comme lu
        </button>
    </div>
    <div id="notifications-list" style="padding: 10px;">
        <div style="padding: 20px; text-align: center; color: #999;">
            Chargement...
        </div>
    </div>
</div>

<script>
    let notificationsOpen = false;
    let profileMenuOpen = false;

    function toggleNotifications() {
        notificationsOpen = !notificationsOpen;
        const dropdown = document.getElementById('notifications-dropdown');
        dropdown.style.display = notificationsOpen ? 'block' : 'none';
        
        if (notificationsOpen) {
            loadNotifications();
        }
        
        if (profileMenuOpen) {
            toggleProfileMenu();
        }
    }

    function toggleProfileMenu() {
        profileMenuOpen = !profileMenuOpen;
        const menu = document.getElementById('profile-menu');
        menu.style.display = profileMenuOpen ? 'block' : 'none';
        
        if (notificationsOpen) {
            toggleNotifications();
        }
    }

    function loadNotifications() {
        axios.get('{{ route("dashboard.notifications.unread-count") }}')
            .then(response => {
                const list = document.getElementById('notifications-list');
                // Charger les notifications récentes
                // TODO: Implémenter le chargement complet
            })
            .catch(error => {
                console.error('Erreur lors du chargement des notifications:', error);
            });
    }

    function markAllAsRead() {
        axios.post('{{ route("dashboard.notifications.read-all") }}')
            .then(response => {
                showNotification('Toutes les notifications ont été marquées comme lues', 'success');
                toggleNotifications();
                location.reload();
            })
            .catch(error => {
                console.error('Erreur:', error);
            });
    }

    // Recherche globale
    let searchTimeout;
    document.getElementById('global-search').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value;
        
        if (query.length < 2) {
            return;
        }
        
        searchTimeout = setTimeout(() => {
            // TODO: Implémenter la recherche
            console.log('Recherche:', query);
        }, 300);
    });

    // Fermer les menus en cliquant ailleurs
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-icon') && !e.target.closest('#notifications-dropdown')) {
            if (notificationsOpen) {
                toggleNotifications();
            }
        }
        
        if (!e.target.closest('.profile-dropdown')) {
            if (profileMenuOpen) {
                toggleProfileMenu();
            }
        }
    });
</script>
