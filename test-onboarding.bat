@echo off
REM Script de test pour le processus d'onboarding (Windows)
REM Usage: test-onboarding.bat [--clean]

echo 🧪 Test du processus d'onboarding Akasi Group
echo ========================================
echo.

REM Vérifier que Laravel est installé
if not exist "artisan" (
    echo ❌ Erreur: Ce script doit être exécuté depuis la racine du projet Laravel
    exit /b 1
)

REM Exécuter les tests
echo ▶️  Exécution des tests d'onboarding...
echo.

php artisan test:onboarding %*

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✅ Tous les tests sont passés avec succès!
    exit /b 0
) else (
    echo.
    echo ❌ Certains tests ont échoué
    exit /b 1
)
