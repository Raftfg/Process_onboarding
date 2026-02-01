<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Akasi Group</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #00286f;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            max-width: 200px;
            max-height: 80px;
            object-fit: contain;
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: #00286f;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input.error {
            border-color: #ef4444;
        }

        .form-group-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group-checkbox input {
            width: auto;
            margin-right: 8px;
        }

        .form-group-checkbox label {
            margin: 0;
            font-weight: normal;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #00286f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            color: #ef4444;
            font-size: 14px;
            margin-top: 5px;
        }

        .success-message {
            background: #10b981;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        @if ($errors->has('email'))
            .form-group:first-of-type input {
                border-color: #ef4444;
            }
        @endif
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
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
                    <img src="{{ $logoUrl }}" alt="{{ $tenantBranding['organization_name'] ?? 'Logo' }}" id="tenant-logo" onerror="this.style.display='none'; document.getElementById('logo-text').style.display='block';">
                    <h1 id="logo-text" style="display: none;">{{ $tenantBranding['organization_name'] ?? 'Akasi Group' }}</h1>
                @else
                    <h1>Akasi Group</h1>
                @endif
            </div>
            <h1>Connexion</h1>
            <p>Accédez à votre espace {{ $tenantBranding['organization_name'] ?? 'Akasi Group' }}</p>
        </div>

        @if(session('success'))
            <div class="success-message">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Adresse email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', $prefilledEmail ?? '') }}" 
                    required 
                    autofocus
                    class="@error('email') error @enderror"
                >
                @error('email')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    class="@error('password') error @enderror"
                >
                @error('password')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group-checkbox">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>
    </div>
</body>
</html>
