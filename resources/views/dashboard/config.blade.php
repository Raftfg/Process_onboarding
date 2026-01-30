<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration du Dashboard - MedKey</title>
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

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .config-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .config-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group select,
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .widgets-list {
            display: grid;
            gap: 15px;
        }

        .widget-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }

        .widget-item:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .widget-item.enabled {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .widget-icon {
            font-size: 32px;
            width: 50px;
            text-align: center;
        }

        .widget-content {
            flex: 1;
        }

        .widget-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
            color: #333;
        }

        .widget-description {
            font-size: 14px;
            color: #666;
        }

        .widget-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #667eea;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .widget-size-select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .widget-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .widget-controls {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Configuration du Dashboard</h1>
            <div class="header-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">Retour au Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('dashboard.config.store') }}">
            @csrf

            <!-- Configuration générale -->
            <div class="config-section">
                <h2>Configuration générale</h2>
                
                <div class="form-group">
                    <label for="theme">Thème</label>
                    <select name="theme" id="theme" required>
                        <option value="light" {{ $config->theme === 'light' ? 'selected' : '' }}>Clair</option>
                        <option value="dark" {{ $config->theme === 'dark' ? 'selected' : '' }}>Sombre</option>
                        <option value="auto" {{ $config->theme === 'auto' ? 'selected' : '' }}>Automatique</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="langue">Langue</label>
                    <select name="langue" id="langue" required>
                        <option value="fr" {{ $config->langue === 'fr' ? 'selected' : '' }}>Français</option>
                        <option value="en" {{ $config->langue === 'en' ? 'selected' : '' }}>English</option>
                        <option value="es" {{ $config->langue === 'es' ? 'selected' : '' }}>Español</option>
                    </select>
                </div>
            </div>

            <!-- Configuration des widgets -->
            <div class="config-section">
                <h2>Widgets du Dashboard</h2>
                <p style="color: #666; margin-bottom: 20px;">Activez ou désactivez les widgets que vous souhaitez voir sur votre dashboard. Vous pouvez également ajuster leur taille.</p>
                
                <div class="widgets-list" id="widgets-list">
                    @foreach($availableWidgets as $index => $widget)
                        @php
                            $widgetConfig = collect($widgetsConfig)->firstWhere('id', $widget['id']);
                            $isEnabled = $widgetConfig !== null;
                            $position = $isEnabled ? ($widgetConfig['position'] ?? $index) : $index;
                            $size = $widgetConfig['size'] ?? $widget['default_size'];
                        @endphp
                        
                        <div class="widget-item {{ $isEnabled ? 'enabled' : '' }}" data-widget-id="{{ $widget['id'] }}">
                            <div class="widget-icon">{{ $widget['icon'] }}</div>
                            <div class="widget-content">
                                <div class="widget-name">{{ $widget['name'] }}</div>
                                <div class="widget-description">{{ $widget['description'] }}</div>
                            </div>
                            <div class="widget-controls">
                                <label class="toggle-switch">
                                    <input 
                                        type="checkbox" 
                                        name="widgets[{{ $index }}][enabled]" 
                                        value="1"
                                        {{ $isEnabled ? 'checked' : '' }}
                                        onchange="updateWidgetState(this)"
                                    >
                                    <span class="toggle-slider"></span>
                                </label>
                                <input type="hidden" name="widgets[{{ $index }}][id]" value="{{ $widget['id'] }}">
                                <input type="hidden" name="widgets[{{ $index }}][position]" value="{{ $position }}" class="widget-position">
                                <select name="widgets[{{ $index }}][size]" class="widget-size-select">
                                    <option value="small" {{ $size === 'small' ? 'selected' : '' }}>Petit</option>
                                    <option value="medium" {{ $size === 'medium' ? 'selected' : '' }}>Moyen</option>
                                    <option value="large" {{ $size === 'large' ? 'selected' : '' }}>Grand</option>
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <script>
                    function updateWidgetState(checkbox) {
                        const widgetItem = checkbox.closest('.widget-item');
                        widgetItem.classList.toggle('enabled', checkbox.checked);
                        updatePositions();
                    }
                    
                    function updatePositions() {
                        const enabledWidgets = document.querySelectorAll('.widget-item.enabled');
                        enabledWidgets.forEach((item, index) => {
                            const positionInput = item.querySelector('.widget-position');
                            if (positionInput) {
                                positionInput.value = index;
                            }
                        });
                    }
                    
                    // Initialiser les positions au chargement
                    document.addEventListener('DOMContentLoaded', function() {
                        updatePositions();
                    });
                </script>
            </div>

            <div class="form-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer la configuration</button>
            </div>
        </form>
    </div>
</body>
</html>

