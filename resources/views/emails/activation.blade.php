<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Confirmez votre adresse email</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, sans-serif !important;}
    </style>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        
        /* Main styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            background-color: #e8f3f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #e8f3f8;
            padding: 40px 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background-color: #e8f3f8;
            padding: 30px 30px 20px;
            text-align: left;
        }
        
        .logo {
            color: #00a3e0;
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
        }
        
        .tagline {
            color: #666666;
            font-size: 14px;
            margin: 0;
            font-weight: normal;
        }
        
        .email-content {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .main-heading {
            font-size: 22px;
            color: #333333;
            margin: 0 0 20px 0;
            font-weight: 600;
        }
        
        .main-message {
            font-size: 15px;
            color: #555555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .cta-container {
            text-align: left;
            margin: 30px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #9dd82d;
            color: #333333 !important;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            background-color: #8bc924;
        }
        
        .footer-text {
            font-size: 13px;
            color: #888888;
            margin: 30px 0 0 0;
            line-height: 1.5;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                padding: 20px 10px;
            }
            
            .email-header,
            .email-content {
                padding: 25px 20px;
            }
            
            .logo {
                font-size: 20px;
            }
            
            .cta-button {
                padding: 12px 28px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <div class="logo">Akasi Group</div>
                <p class="tagline">Votre espace de travail ultime</p>
            </div>
            
            <!-- Content -->
            <div class="email-content">
                <h1 class="main-heading">Confirmez votre adresse email</h1>
                
                <p class="main-message">
                    Créez un mot de passe pour confirmer votre email et continuer à utiliser Akasi Group.
                </p>
                
                <div class="cta-container">
                    <a href="{{ $activationUrl }}" class="cta-button">
                        Créer un mot de passe
                    </a>
                </div>
                
                <p class="footer-text">
                    Ce lien est valable pendant {{ $expiresInDays }} jours. Si vous ne l'utilisez pas dans ce délai, vous devrez demander un nouveau lien.
                </p>
                
                <p class="footer-text">
                    Ignorez ce message si vous ne l'avez pas demandé.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
