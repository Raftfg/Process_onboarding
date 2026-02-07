<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration de votre espace - {{ config('app.brand_name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .questionnaire-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            padding: 40px;
            position: relative;
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #00286f;
            font-size: 24px;
            font-weight: 700;
        }

        .welcome-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .question-title {
            font-size: 22px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
        }

        .question-subtitle {
            font-size: 14px;
            color: #718096;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .option-button {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            color: #2d3748;
            font-weight: 500;
        }

        .option-button:hover {
            border-color: #667eea;
            background: #f7fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .option-button.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-back {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        .btn-continue {
            background: #667eea;
            color: white;
        }

        .btn-continue:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-continue:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
        }

        .btn-skip {
            background: transparent;
            color: #718096;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-skip:hover {
            color: #4a5568;
        }

        .progress-indicator {
            text-align: center;
            color: #718096;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 30px;
        }

        .step-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e2e8f0;
            transition: all 0.3s;
        }

        .step-dot.active {
            background: #667eea;
            width: 24px;
            border-radius: 4px;
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .questionnaire-container {
                padding: 20px;
            }

            .options-grid {
                grid-template-columns: 1fr;
            }

            .navigation {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="questionnaire-container">
        <div class="logo">
            <h1>{{ config('app.brand_name') }}</h1>
        </div>

        <p class="welcome-text">
            Bienvenue ! Configurons votre espace - quelques questions rapides pour commencer.
        </p>

        <div class="step-indicator">
            <div class="step-dot active" id="stepDot1"></div>
            <div class="step-dot" id="stepDot2"></div>
            <div class="step-dot" id="stepDot3"></div>
        </div>

        <!-- Étape 1: Taille de l'entreprise -->
        <div class="step-content active" id="step1">
            <h2 class="question-title">Quelle est la taille de votre entreprise ?</h2>
            <div class="options-grid">
                <button class="option-button" data-value="just-me">Juste moi</button>
                <button class="option-button" data-value="2-5">2-5 personnes</button>
                <button class="option-button" data-value="6-50">6-50 personnes</button>
                <button class="option-button" data-value="51-100">51-100 personnes</button>
                <button class="option-button" data-value="101-250">101-250 personnes</button>
                <button class="option-button" data-value="250+">Plus de 250 personnes</button>
            </div>
        </div>

        <!-- Étape 2: Secteur d'activité -->
        <div class="step-content" id="step2">
            <h2 class="question-title">Dans quel secteur travaillez-vous ?</h2>
            <div class="options-grid">
                <button class="option-button" data-value="other">Autre</button>
                <button class="option-button" data-value="ecommerce">E-commerce</button>
                <button class="option-button" data-value="construction">Construction</button>
                <button class="option-button" data-value="digital-marketing">Marketing digital</button>
                <button class="option-button" data-value="education">Éducation</button>
                <button class="option-button" data-value="financial">Finance</button>
                <button class="option-button" data-value="healthcare">Santé</button>
                <button class="option-button" data-value="horeca">Hôtellerie/Restauration</button>
                <button class="option-button" data-value="it">IT</button>
                <button class="option-button" data-value="legal">Juridique</button>
                <button class="option-button" data-value="manufacturing">Industrie</button>
                <button class="option-button" data-value="non-profit">Organisation à but non lucratif</button>
                <button class="option-button" data-value="real-estate">Immobilier</button>
                <button class="option-button" data-value="retail">Commerce de détail</button>
                <button class="option-button" data-value="consulting">Conseil</button>
                <button class="option-button" data-value="transportation">Transport</button>
                <button class="option-button" data-value="travel">Voyage</button>
            </div>
        </div>

        <!-- Étape 3: Objectif principal -->
        <div class="step-content" id="step3">
            <h2 class="question-title">Quel est votre objectif principal ?</h2>
            <p class="question-subtitle">
                Cette sélection adaptera l'interface à vos besoins principaux, tout en gardant l'accès à tous les outils disponibles. Vous pourrez modifier votre sélection plus tard.
            </p>
            <div class="options-grid" style="grid-template-columns: 1fr;">
                <button class="option-button" data-value="crm">Augmenter les ventes/clients (CRM)</button>
                <button class="option-button" data-value="projects">Gérer les projets/tâches</button>
                <button class="option-button" data-value="website">Créer des sites web</button>
                <button class="option-button" data-value="communication">Améliorer la communication interne</button>
                <button class="option-button" data-value="hr">Gérer les employés (RH)</button>
                <button class="option-button" data-value="automation">Automatiser les processus</button>
            </div>
        </div>

        <div class="navigation">
            <div>
                <button class="btn btn-back" id="btnBack" style="display: none;">Retour</button>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <button class="btn-skip" id="btnSkip">Passer</button>
                <button class="btn btn-continue" id="btnContinue" disabled>
                    Continuer <span id="stepCounter">1/3</span>
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 3;
        const answers = {};

        // Initialiser
        $(document).ready(function() {
            updateStepIndicator();
            updateNavigation();

            // Gestion des clics sur les options
            $('.option-button').on('click', function() {
                $(this).siblings().removeClass('selected');
                $(this).addClass('selected');
                $('#btnContinue').prop('disabled', false);
            });

            // Bouton Continuer
            $('#btnContinue').on('click', function() {
                const selectedOption = $(`#step${currentStep} .option-button.selected`);
                if (selectedOption.length > 0) {
                    const questionKey = getQuestionKey(currentStep);
                    answers[questionKey] = selectedOption.data('value');
                    
                    if (currentStep < totalSteps) {
                        goToNextStep();
                    } else {
                        completeQuestionnaire();
                    }
                }
            });

            // Bouton Retour
            $('#btnBack').on('click', function() {
                if (currentStep > 1) {
                    goToPreviousStep();
                }
            });

            // Bouton Passer
            $('#btnSkip').on('click', function() {
                completeQuestionnaire();
            });
        });

        function getQuestionKey(step) {
            const keys = {
                1: 'company_size',
                2: 'industry',
                3: 'primary_goal'
            };
            return keys[step] || `question_${step}`;
        }

        function goToNextStep() {
            $(`#step${currentStep}`).removeClass('active');
            currentStep++;
            $(`#step${currentStep}`).addClass('active');
            updateStepIndicator();
            updateNavigation();
            $('#btnContinue').prop('disabled', true);
        }

        function goToPreviousStep() {
            $(`#step${currentStep}`).removeClass('active');
            currentStep--;
            $(`#step${currentStep}`).addClass('active');
            updateStepIndicator();
            updateNavigation();
            
            // Restaurer la sélection précédente si elle existe
            const questionKey = getQuestionKey(currentStep);
            if (answers[questionKey]) {
                $(`#step${currentStep} .option-button[data-value="${answers[questionKey]}"]`).addClass('selected');
                $('#btnContinue').prop('disabled', false);
            } else {
                $('#btnContinue').prop('disabled', true);
            }
        }

        function updateStepIndicator() {
            $('.step-dot').each(function(index) {
                if (index + 1 === currentStep) {
                    $(this).addClass('active');
                } else if (index + 1 < currentStep) {
                    $(this).removeClass('active').css('background', '#667eea');
                } else {
                    $(this).removeClass('active').css('background', '#e2e8f0');
                }
            });
            $('#stepCounter').text(`${currentStep}/${totalSteps}`);
        }

        function updateNavigation() {
            if (currentStep === 1) {
                $('#btnBack').hide();
            } else {
                $('#btnBack').show();
            }

            if (currentStep === totalSteps) {
                $('#btnContinue').text('Terminer');
            } else {
                $('#btnContinue').html(`Continuer <span id="stepCounter">${currentStep}/${totalSteps}</span>`);
            }
        }

        function completeQuestionnaire() {
            // Désactiver les boutons pendant l'envoi
            $('#btnContinue, #btnSkip, #btnBack').prop('disabled', true);
            $('#btnContinue').text('Redirection...');

            // Envoyer les réponses au serveur (optionnel)
            const subdomain = '{{ $subdomain ?? "" }}';
            const email = '{{ $email ?? "" }}';
            
            if (subdomain && email) {
                $.ajax({
                    url: '{{ route("onboarding.questionnaire.save") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    data: JSON.stringify({
                        answers: answers,
                        subdomain: subdomain,
                        email: email
                    }),
                    success: function(response) {
                        // Rediriger vers le dashboard
                        redirectToDashboard();
                    },
                    error: function() {
                        // Même en cas d'erreur, rediriger vers le dashboard
                        redirectToDashboard();
                    }
                });
            } else {
                // Si pas de données, rediriger directement
                redirectToDashboard();
            }
        }

        function redirectToDashboard() {
            const subdomain = '{{ $subdomain ?? "" }}';
            const autoLoginToken = '{{ $auto_login_token ?? "" }}';
            const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            const port = window.location.port || (isLocal ? '8000' : '');
            const protocol = window.location.protocol;

            if (subdomain && autoLoginToken) {
                if (isLocal) {
                    window.location.href = `${protocol}//${subdomain}.localhost:${port}/dashboard?auto_login_token=${autoLoginToken}`;
                } else {
                    const baseDomain = '{{ config("app.brand_domain") }}';
                    window.location.href = `${protocol}//${subdomain}.${baseDomain}/dashboard?auto_login_token=${autoLoginToken}`;
                }
            } else if (subdomain) {
                if (isLocal) {
                    window.location.href = `${protocol}//${subdomain}.localhost:${port}/dashboard`;
                } else {
                    const baseDomain = '{{ config("app.brand_domain") }}';
                    window.location.href = `${protocol}//${subdomain}.${baseDomain}/dashboard`;
                }
            }
        }
    </script>
</body>
</html>
