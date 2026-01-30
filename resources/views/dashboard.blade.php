<!DOCTYPE html>
<html lang="fr">
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

        .welcome-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .welcome-card h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }

        .welcome-card p {
            color: #666;
            font-size: 16px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

        .info-card .label {
            font-size: 14px;
            color: #666;
        }

        .details-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .details-section h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }

        .detail-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
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

        .detail-value {
            color: #333;
            flex: 1;
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
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>MedKey - Tableau de bord</h1>
            <div class="header-actions">
                @auth
                <div class="user-info">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->email ?? 'A', 0, 1)) }}
                    </div>
                    <span>{{ Auth::user()->name ?? Auth::user()->email ?? 'Administrateur' }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-logout">Déconnexion</button>
                </form>
                @else
                <a href="{{ route('login') }}" class="btn-logout">Se connecter</a>
                @endauth
            </div>
        </div>
    </div>

    <div class="container">
        @if(isset($onboarding))
            <div class="welcome-card">
                <h2>Bienvenue, {{ Auth::user()->name ?? Auth::user()->email ?? 'Administrateur' }} !</h2>
                <p>Votre espace MedKey est maintenant configuré et prêt à l'emploi.</p>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <h3>Statut</h3>
                    <div class="value">
                        <span class="status-badge completed">Actif</span>
                    </div>
                    <div class="label">Système opérationnel</div>
                </div>

                <div class="info-card">
                    <h3>Sous-domaine</h3>
                    <div class="value">{{ $onboarding->subdomain ?? 'N/A' }}</div>
                    <div class="label">Identifiant unique</div>
                </div>

                <div class="info-card">
                    <div class="value">{{ $onboarding->completed_at ? $onboarding->completed_at->format('d/m/Y') : 'N/A' }}</div>
                    <div class="label">Date d'activation</div>
                </div>
            </div>

            <div class="details-section">
                <h3>Informations de l'hôpital</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Nom de l'hôpital</div>
                    <div class="detail-value">{{ $onboarding->hospital_name ?? 'N/A' }}</div>
                </div>

                @if($onboarding->hospital_address)
                <div class="detail-row">
                    <div class="detail-label">Adresse</div>
                    <div class="detail-value">{{ $onboarding->hospital_address }}</div>
                </div>
                @endif

                @if($onboarding->hospital_phone)
                <div class="detail-row">
                    <div class="detail-label">Téléphone</div>
                    <div class="detail-value">{{ $onboarding->hospital_phone }}</div>
                </div>
                @endif

                @if($onboarding->hospital_email)
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">{{ $onboarding->hospital_email }}</div>
                </div>
                @endif
            </div>

            <div class="details-section" style="margin-top: 20px;">
                <h3>Informations administrateur</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Nom complet</div>
                    <div class="detail-value">{{ $onboarding->admin_first_name ?? '' }} {{ $onboarding->admin_last_name ?? '' }}</div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">{{ $onboarding->admin_email ?? Auth::user()->email ?? 'N/A' }}</div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Email connecté</div>
                    <div class="detail-value">{{ Auth::user()->email ?? 'N/A' }}</div>
                </div>
            </div>
        @else
            <div class="welcome-card">
                <h2>Bienvenue, {{ Auth::user()->name ?? Auth::user()->email ?? 'Administrateur' }} !</h2>
                <p>Aucune information d'onboarding trouvée pour ce sous-domaine.</p>
                @if(isset($subdomain))
                <p style="margin-top: 10px; color: #666; font-size: 14px;">Sous-domaine: {{ $subdomain }}</p>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
