<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choisissez votre domaine - MedKey</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            font-weight: bold;
        }

        h1 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
        }

        .email-badge {
            display: inline-block;
            background: #f0f0f0;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #667eea;
            font-weight: 500;
            margin-bottom: 30px;
        }

        .domains-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .domain-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .domain-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }

        .domain-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .domain-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .domain-subdomain {
            font-size: 14px;
            color: #667eea;
            font-weight: 500;
            background: #f0f4ff;
            padding: 4px 12px;
            border-radius: 6px;
        }

        .domain-info {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }

        .domain-info-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-badge.admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-badge.user {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-badge.manager {
            background: #d1fae5;
            color: #065f46;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">MK</div>
                <h1>Choisissez votre domaine</h1>
                <p class="subtitle">Sélectionnez le domaine auquel vous souhaitez vous connecter</p>
                <div class="email-badge">{{ $email }}</div>
            </div>

            @if(!empty($tenants))
                <div class="domains-list">
                    @foreach($tenants as $item)
                        @php
                            $tenant = $item['tenant'];
                            $user = $item['user'];
                            $subdomain = $tenant->subdomain;
                            $loginUrl = subdomain_url($subdomain, '/login') . '?email=' . urlencode($email);
                        @endphp
                        <a href="{{ $loginUrl }}" class="domain-card">
                            <div class="domain-header">
                                <div class="domain-name">{{ $tenant->name }}</div>
                                <div class="domain-subdomain">{{ $subdomain }}</div>
                            </div>
                            <div class="domain-info">
                                <div class="domain-info-item">
                                    <span>👤</span>
                                    <span>{{ $user['name'] }}</span>
                                </div>
                                <div class="domain-info-item">
                                    <span class="role-badge {{ $user['role'] }}">{{ $user['role'] }}</span>
                                </div>
                                @if($tenant->email)
                                <div class="domain-info-item">
                                    <span>📧</span>
                                    <span>{{ $tenant->email }}</span>
                                </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <p>Aucun domaine trouvé pour cet email.</p>
                </div>
            @endif

            <a href="{{ route('start') }}" class="back-link">← Retour</a>
        </div>
    </div>
</body>
</html>

