<div class="sidebar">
    <div class="sidebar-header">
        @if(isset($tenantBranding['logo_url']) && $tenantBranding['logo_url'])
            @php
                // S'assurer que l'URL est absolue
                $logoUrl = $tenantBranding['logo_url'];
                if (!filter_var($logoUrl, FILTER_VALIDATE_URL)) {
                    // Si c'est un chemin relatif, le rendre absolu
                    if (strpos($logoUrl, '/storage/') === 0) {
                        $logoUrl = request()->getSchemeAndHttpHost() . $logoUrl;
                    } else {
                        $logoUrl = asset($logoUrl);
                    }
                }
            @endphp
            <img src="{{ $logoUrl }}" alt="{{ $tenantBranding['organization_name'] ?? 'Logo' }}" onerror="this.style.display='none';">
        @endif
        <h1>{{ $tenantBranding['organization_name'] ?? 'Akasi Group' }}</h1>
    </div>
    
    <nav class="sidebar-nav">
        @php
            $menuItems = $tenantMenu['items'] ?? [];
            $routeMap = [
                'dashboard' => ['route' => 'dashboard', 'pattern' => 'dashboard'],
                'users' => ['route' => 'dashboard.users.index', 'pattern' => 'dashboard.users.*'],
                'activities' => ['route' => 'dashboard.activities', 'pattern' => 'dashboard.activities*'],
                'reports' => ['route' => 'dashboard.reports', 'pattern' => 'dashboard.reports*'],
                'settings' => ['route' => 'dashboard.settings', 'pattern' => 'dashboard.settings*'],
                'customization' => ['route' => 'dashboard.customization', 'pattern' => 'dashboard.customization*'],
            ];
        @endphp
        
        @foreach($menuItems as $item)
            @if(($item['enabled'] ?? true) && isset($routeMap[$item['key']]))
                @php
                    // Préserver le token auto_login_token dans les liens du menu
                    $menuUrl = route($routeMap[$item['key']]['route']);
                    if (request()->has('auto_login_token')) {
                        $token = request()->query('auto_login_token');
                        $menuUrl .= (str_contains($menuUrl, '?') ? '&' : '?') . 'auto_login_token=' . $token;
                    }
                @endphp
                <a href="{{ $menuUrl }}" class="nav-item {{ request()->routeIs($routeMap[$item['key']]['pattern']) ? 'active' : '' }}">
                    <i>{{ $item['icon'] ?? '📋' }}</i> {{ $item['label'] ?? ucfirst($item['key']) }}
                </a>
            @endif
        @endforeach
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-menu">
            <div class="user-avatar">
                {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->email ?? 'A', 0, 1)) }}
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; font-size: 14px;">{{ Auth::user()->name ?? 'Utilisateur' }}</div>
                <div style="font-size: 12px; opacity: 0.8;">{{ Auth::user()->email ?? '' }}</div>
            </div>
        </div>
    </div>
</div>
