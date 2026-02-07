<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.brand_name') }}</title>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: {{ $tenantCssVariables['--primary-color'] ?? '#00286f' }};
            --primary-dark: {{ $tenantCssVariables['--primary-dark'] ?? '#001d4d' }};
            --secondary-color: {{ $tenantCssVariables['--secondary-color'] ?? '#001d4d' }};
            --accent-color: {{ $tenantCssVariables['--accent-color'] ?? '#10b981' }};
            --sidebar-width: 260px;
            --header-height: 70px;
            --bg-color: {{ $tenantCssVariables['--bg-color'] ?? '#f5f7fa' }};
            --text-color: #333;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header img {
            max-height: 40px;
            max-width: 150px;
            object-fit: contain;
        }

        .sidebar-header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: white;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: white;
            font-weight: 600;
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        .nav-badge {
            float: right;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
        }

        /* Header */
        .header {
            height: var(--header-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
        }

        .header-left {
            flex: 1;
            max-width: 500px;
        }

        @media (max-width: 768px) {
            .header-left {
                max-width: 100%;
            }

            .search-box input {
                font-size: 14px;
                padding: 8px 35px 8px 12px;
            }

            .profile-button span {
                display: none;
            }

            .header-right {
                gap: 10px;
            }

            #notifications-dropdown {
                width: calc(100% - 40px);
                right: 20px;
                left: 20px;
                max-width: 350px;
            }
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            padding: 8px;
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }

        .profile-dropdown {
            position: relative;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .profile-button:hover {
            background: var(--bg-color);
        }

        /* Content Area */
        .content-area {
            padding: 30px;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-card-title {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 40, 111, 0.1);
            color: var(--primary-color);
        }

        .stat-card-value {
            font-size: 32px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .stat-card-label {
            font-size: 14px;
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                width: var(--sidebar-width);
                z-index: 2000;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1500;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .header {
                padding: 0 15px;
            }

            .header h2 {
                font-size: 18px;
            }

            .content-area {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card-value {
                font-size: 24px !important;
            }

            .card {
                padding: 15px;
            }

            .tab-button {
                padding: 10px 15px !important;
                font-size: 14px !important;
            }

            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }

            .card {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 480px) {
            .sidebar-header h1 {
                font-size: 18px;
            }

            .sidebar-header img {
                max-height: 30px;
                max-width: 100px;
            }

            .nav-item {
                padding: 10px 15px;
                font-size: 14px;
            }

            .header {
                height: 60px;
                padding: 0 10px;
            }

            .header h2 {
                font-size: 16px;
            }

            .content {
                padding: 10px;
            }
        }

        /* Loading */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 40, 111, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    
    @stack('styles')
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <div class="dashboard-container">
        @include('dashboard.partials.sidebar')
        
        <div class="main-content">
            @include('dashboard.partials.header')
            
            <div class="content-area">
                {{-- Alertes statiques supprimées au profit de SweetAlert2 --}}

                @yield('content')
            </div>
        </div>
    </div>

    <script>
        // IMPORTANT: Préserver le token auto_login_token dans toutes les URLs du dashboard
        // Ce script DOIT s'exécuter AVANT le chargement d'Axios pour intercepter correctement les requêtes
        (function() {
            // Récupérer le token de l'URL actuelle
            const urlParams = new URLSearchParams(window.location.search);
            const autoLoginToken = urlParams.get('auto_login_token');
            
            if (autoLoginToken) {
                // Stocker le token dans une variable globale accessible
                window.autoLoginToken = autoLoginToken;
                
                // Intercepter tous les clics sur les liens AVANT le chargement du DOM
                document.addEventListener('DOMContentLoaded', function() {
                    // Intercepter tous les clics sur les liens
                    document.addEventListener('click', function(e) {
                        const link = e.target.closest('a');
                        if (link && link.href) {
                            // Vérifier si c'est un lien interne (même domaine)
                            try {
                                const linkUrl = new URL(link.href, window.location.origin);
                                const currentUrl = new URL(window.location.href);
                                
                                // Si c'est le même domaine et que le token n'est pas déjà présent
                                if (linkUrl.hostname === currentUrl.hostname && !linkUrl.searchParams.has('auto_login_token')) {
                                    // Ajouter le token à l'URL
                                    linkUrl.searchParams.set('auto_login_token', autoLoginToken);
                                    link.href = linkUrl.toString();
                                }
                            } catch (err) {
                                // Ignorer les erreurs pour les liens invalides
                            }
                        }
                    });
                });
                
                // Intercepter les requêtes Axios APRÈS le chargement d'Axios
                // Utiliser un MutationObserver pour détecter quand Axios est chargé
                const checkAxios = setInterval(function() {
                    if (typeof axios !== 'undefined') {
                        clearInterval(checkAxios);
                        
                        // Configuration Axios
                        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        
                        // Intercepter les requêtes Axios pour ajouter le token aux URLs
                        const originalGet = axios.get;
                        axios.get = function(url, config) {
                            if (typeof url === 'string') {
                                try {
                                    // Si l'URL est relative, la convertir en URL absolue
                                    const urlObj = url.startsWith('http') 
                                        ? new URL(url) 
                                        : new URL(url, window.location.origin);
                                    
                                    // Vérifier si c'est le même domaine
                                    if (urlObj.hostname === window.location.hostname && !urlObj.searchParams.has('auto_login_token')) {
                                        urlObj.searchParams.set('auto_login_token', autoLoginToken);
                                        url = urlObj.pathname + urlObj.search;
                                    }
                                } catch (err) {
                                    // Si l'URL est invalide, essayer d'ajouter le token manuellement
                                    if (url.includes('?')) {
                                        url += '&auto_login_token=' + autoLoginToken;
                                    } else {
                                        url += '?auto_login_token=' + autoLoginToken;
                                    }
                                }
                            }
                            return originalGet.call(this, url, config);
                        };
                        
                        // Intercepter aussi axios.post, axios.put, axios.delete, etc.
                        ['post', 'put', 'patch', 'delete'].forEach(function(method) {
                            const originalMethod = axios[method];
                            axios[method] = function(url, data, config) {
                                if (typeof url === 'string') {
                                    try {
                                        const urlObj = url.startsWith('http') 
                                            ? new URL(url) 
                                            : new URL(url, window.location.origin);
                                        
                                        if (urlObj.hostname === window.location.hostname && !urlObj.searchParams.has('auto_login_token')) {
                                            urlObj.searchParams.set('auto_login_token', autoLoginToken);
                                            url = urlObj.pathname + urlObj.search;
                                        }
                                    } catch (err) {
                                        if (url.includes('?')) {
                                            url += '&auto_login_token=' + autoLoginToken;
                                        } else {
                                            url += '?auto_login_token=' + autoLoginToken;
                                        }
                                    }
                                }
                                return originalMethod.call(this, url, data, config);
                            };
                        });
                    }
                }, 50); // Vérifier toutes les 50ms jusqu'à ce qu'Axios soit chargé
                
                // Intercepter les redirections JavaScript
                const originalLocationAssign = window.location.assign;
                window.location.assign = function(url) {
                    if (typeof url === 'string') {
                        try {
                            const urlObj = new URL(url, window.location.origin);
                            if (urlObj.hostname === window.location.hostname && !urlObj.searchParams.has('auto_login_token')) {
                                urlObj.searchParams.set('auto_login_token', autoLoginToken);
                                url = urlObj.toString();
                            }
                        } catch (err) {
                            // Ignorer les erreurs
                        }
                    }
                    return originalLocationAssign.call(this, url);
                };
            }
        })();
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        // Configuration Axios (si le token n'a pas été configuré par le script précédent)
        if (typeof axios !== 'undefined' && !axios.defaults.headers.common['X-CSRF-TOKEN']) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        
        // Fonction utilitaire pour les notifications
        function showNotification(message, type = 'info') {
            // Implémentation simple - peut être améliorée avec une librairie
            const colors = {
                success: '#d1fae5',
                error: '#fee2e2',
                info: '#dbeafe',
                warning: '#fef3c7'
            };
            
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type] || colors.info};
                color: #333;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }
        }

        // Close sidebar when clicking on a link (mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-item');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                });
            });
        });
    </script>
    
    <script>
        $(document).ready(function() {
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: "{{ session('success') }}",
                    confirmButtonColor: '#00286f',
                    timer: 5000,
                    timerProgressBar: true
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: "{{ session('error') }}",
                    confirmButtonColor: '#00286f'
                });
            @endif
        });
    </script>
    
    @stack('scripts')
</body>
</html>
