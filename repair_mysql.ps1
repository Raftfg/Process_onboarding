# Script PowerShell pour réparer la table MySQL global_priv
# Exécuter en tant qu'administrateur

Write-Host "=== Script de réparation MySQL global_priv ===" -ForegroundColor Cyan
Write-Host ""

# Demander les credentials MySQL
$mysqlUser = Read-Host "Nom d'utilisateur MySQL (root)"
$mysqlPassword = Read-Host "Mot de passe MySQL" -AsSecureString
$mysqlPasswordPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($mysqlPassword)
)

Write-Host ""
Write-Host "Choisissez une méthode de réparation:" -ForegroundColor Yellow
Write-Host "1. Réparation automatique (REPAIR TABLE) - RECOMMANDÉ"
Write-Host "2. Supprimer et recréer la table - DANGEREUX"
Write-Host ""
$choice = Read-Host "Votre choix (1 ou 2)"

if ($choice -eq "1") {
    Write-Host ""
    Write-Host "Exécution de la réparation automatique..." -ForegroundColor Green
    
    $sqlCommands = @"
USE mysql;
REPAIR TABLE global_priv;
CHECK TABLE global_priv;
FLUSH PRIVILEGES;
"@
    
    $sqlCommands | mysql -u $mysqlUser -p$mysqlPasswordPlain 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ Réparation terminée avec succès!" -ForegroundColor Green
        Write-Host "Testez maintenant la création d'un utilisateur MySQL." -ForegroundColor Yellow
    } else {
        Write-Host ""
        Write-Host "❌ La réparation automatique a échoué." -ForegroundColor Red
        Write-Host "Vous pouvez essayer la méthode 2 (supprimer et recréer)." -ForegroundColor Yellow
    }
    
} elseif ($choice -eq "2") {
    Write-Host ""
    Write-Host "⚠️  ATTENTION: Cette opération va supprimer tous les privilèges utilisateurs!" -ForegroundColor Red
    $confirm = Read-Host "Êtes-vous sûr de vouloir continuer? (oui/non)"
    
    if ($confirm -ne "oui") {
        Write-Host "Opération annulée." -ForegroundColor Yellow
        exit
    }
    
    Write-Host ""
    Write-Host "Exécution de la suppression et recréation..." -ForegroundColor Green
    
    # Lire le script SQL
    $sqlScript = Get-Content -Path "repair_mysql_global_priv.sql" -Raw
    
    $sqlScript | mysql -u $mysqlUser -p$mysqlPasswordPlain 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ Table recréée avec succès!" -ForegroundColor Green
        Write-Host "⚠️  Vous devrez peut-être recréer vos utilisateurs MySQL." -ForegroundColor Yellow
    } else {
        Write-Host ""
        Write-Host "❌ Erreur lors de la recréation." -ForegroundColor Red
    }
    
} else {
    Write-Host "Choix invalide." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Vérification finale..." -ForegroundColor Cyan
$verifySql = @"
USE mysql;
CHECK TABLE global_priv;
SELECT COUNT(*) AS user_count FROM global_priv;
"@

$verifySql | mysql -u $mysqlUser -p$mysqlPasswordPlain 2>&1

Write-Host ""
Write-Host "=== Fin du script ===" -ForegroundColor Cyan
