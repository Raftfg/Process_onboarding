<!DOCTYPE html>
<html lang="{{ $dashboardConfig->langue ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - MedKey</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        body[data-theme="dark"] {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
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
            font-size: 18px;
        }

        .btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-block;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .widgets-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .widget {
            display: contents;
        }

        .widget-small {
            grid-column: span 1;
        }

        .widget-medium {
            grid-column: span 1;
        }

        .widget-large {
            grid-column: span 2;
        }

        .welcome-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body[data-theme="dark"] .welcome-card {
            background: #2a2a2a;
            color: #e0e0e0;
        }

        .welcome-card h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }

        body[data-theme="dark"] .welcome-card h2 {
            color: #e0e0e0;
        }

        .welcome-card p {
            color: #666;
            font-size: 16px;
        }

        body[data-theme="dark"] .welcome-card p {
            color: #b0b0b0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body[data-theme="dark"] .info-card {
            background: #2a2a2a;
            color: #e0e0e0;
        }

        .info-card h3 {
            font-size: 14px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .info-card .value {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        body[data-theme="dark"] .info-card .value {
            color: #e0e0e0;
        }

        .info-card .label {
            font-size: 14px;
            color: #666;
        }

        body[data-theme="dark"] .info-card .label {
            color: #b0b0b0;
        }

        .details-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body[data-theme="dark"] .details-section {
            background: #2a2a2a;
            color: #e0e0e0;
        }

        .details-section h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        body[data-theme="dark"] .details-section h3 {
            color: #e0e0e0;
        }

        .detail-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        body[data-theme="dark"] .detail-row {
            border-bottom-color: #404040;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            width: 200px;
            flex-shrink: 0;
        }

        body[data-theme="dark"] .detail-label {
            color: #b0b0b0;
        }

        .detail-value {
            color: #333;
            flex: 1;
        }

        body[data-theme="dark"] .detail-value {
            color: #e0e0e0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .widgets-container {
                grid-template-columns: 1fr;
            }

            .widget-large {
                grid-column: span 1;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
    @if(isset($dashboardConfig) && $dashboardConfig->theme === 'dark')
        <script>
            document.documentElement.setAttribute('data-theme', 'dark');
        </script>
    @endif
</head>
<body data-theme="{{ $dashboardConfig->theme ?? 'light' }}">
    <div class="header">
        <div class="header-content">
            <h1>MedKey - Tableau de bord</h1>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <span>{{ Auth::user()->name ?? 'Administrateur' }}</span>
                </div>
                <a href="{{ route('dashboard.config') }}" class="btn">⚙️ Configurer</a>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-logout">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        @if(isset($widgetsConfig) && !empty($widgetsConfig))
            <div class="widgets-container">
                @foreach($widgetsConfig as $widget)
                    @php
                        $widgetId = $widget['id'] ?? '';
                        $widgetSize = $widget['size'] ?? 'medium';
                        $widgetSettings = $widget['settings'] ?? [];
                    @endphp
                    
                    @switch($widgetId)
                        @case('welcome')
                            @include('dashboard.widgets.welcome', ['size' => $widgetSize, 'tenant' => $tenant ?? null])
                            @break
                        @case('tenant_info')
                            @include('dashboard.widgets.tenant_info', ['size' => $widgetSize, 'tenant' => $tenant ?? null])
                            @break
                        @case('user_info')
                            @include('dashboard.widgets.user_info', ['size' => $widgetSize])
                            @break
                        @case('stats')
                            @include('dashboard.widgets.stats', ['size' => $widgetSize, 'tenant' => $tenant ?? null])
                            @break
                        @case('quick_actions')
                            @include('dashboard.widgets.quick_actions', ['size' => $widgetSize])
                            @break
                        @case('recent_activity')
                            @include('dashboard.widgets.recent_activity', ['size' => $widgetSize, 'tenant' => $tenant ?? null])
                            @break
                    @endswitch
                @endforeach
            </div>
        @else
            {{-- Affichage par défaut si aucun widget n'est configuré --}}
            <div class="welcome-card">
                <h2>Bienvenue, {{ Auth::user()->name ?? 'Administrateur' }} !</h2>
                <p>Votre espace MedKey est maintenant configuré et prêt à l'emploi.</p>
                <p style="margin-top: 15px;">
                    <a href="{{ route('dashboard.config') }}" class="btn" style="background: #667eea; color: white;">
                        ⚙️ Configurer votre dashboard
                    </a>
                </p>
            </div>
        @endif
    </div>
</body>
</html>
