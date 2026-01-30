<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - MedKey</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .change-password-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }

        .change-password-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .change-password-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .change-password-header p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
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

        .error-message {
            color: #ef4444;
            font-size: 14px;
            margin-top: 5px;
        }

        .btn-change-password {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-change-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-change-password:active {
            transform: translateY(0);
        }

        .password-requirements {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
        }

        .password-requirements ul {
            margin: 10px 0 0 20px;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="change-password-header">
            <h1>Changer le mot de passe</h1>
            <p>Vous devez changer votre mot de passe pour continuer</p>
        </div>

        <div class="alert alert-warning">
            <strong>⚠️ Important :</strong> Pour des raisons de sécurité, vous devez modifier votre mot de passe avant de continuer.
        </div>

        <form method="POST" action="{{ route('password.change') }}">
            @csrf

            <div class="form-group">
                <label for="current_password">Mot de passe actuel *</label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password" 
                    required 
                    autofocus
                    class="@error('current_password') error @enderror"
                >
                @error('current_password')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Nouveau mot de passe *</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    minlength="8"
                    class="@error('password') error @enderror"
                >
                @error('password')
                    <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmer le nouveau mot de passe *</label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    required
                    minlength="8"
                >
            </div>

            <div class="password-requirements">
                <strong>Exigences du mot de passe :</strong>
                <ul>
                    <li>Minimum 8 caractères</li>
                    <li>Doit être différent de l'ancien mot de passe</li>
                </ul>
            </div>

            <button type="submit" class="btn-change-password">Changer le mot de passe</button>
        </form>
    </div>
</body>
</html>
