# Script PowerShell pour configurer Acrylic DNS Proxy
# Exécutez ce script en tant qu'administrateur
#
# Si vous obtenez une erreur de politique d'exécution, exécutez :
# Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

param(
    [switch]$Uninstall
)

$ErrorActionPreference = "Stop"

# Vérifier les droits administrateur
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "❌ Ce script doit être exécuté en tant qu'administrateur !" -ForegroundColor Red
    Write-Host "Clic droit sur PowerShell → Exécuter en tant qu'administrateur" -ForegroundColor Yellow
    exit 1
}

$acrylicPath = "C:\Program Files (x86)\Acrylic DNS Proxy"
$configFile = Join-Path $acrylicPath "AcrylicConfiguration.ini"

function Write-Step {
    param([string]$Message)
    Write-Host "`n▶ $Message" -ForegroundColor Cyan
}

function Write-Success {
    param([string]$Message)
    Write-Host "✅ $Message" -ForegroundColor Green
}

function Write-Error {
    param([string]$Message)
    Write-Host "❌ $Message" -ForegroundColor Red
}

function Write-Warning {
    param([string]$Message)
    Write-Host "⚠️  $Message" -ForegroundColor Yellow
}

function Test-AcrylicInstalled {
    if (-not (Test-Path $acrylicPath)) {
        Write-Error "Acrylic DNS Proxy n'est pas installé dans $acrylicPath"
        Write-Host "`nTéléchargez Acrylic depuis : https://sourceforge.net/projects/acrylic/" -ForegroundColor Yellow
        return $false
    }
    return $true
}

function Install-AcrylicConfig {
    Write-Step "Configuration d'Acrylic DNS Proxy..."
    
    $acrylicHostsFile = Join-Path $acrylicPath "AcrylicHosts.txt"
    $needsRestart = $false
    
    # Acrylic utilise AcrylicHosts.txt pour les mappings DNS
    if (-not (Test-Path $acrylicHostsFile)) {
        Write-Error "Fichier AcrylicHosts.txt introuvable : $acrylicHostsFile"
        return @{ Success = $false; NeedsRestart = $false }
    }
    
    try {
        # Sauvegarder le fichier hosts
        $backupFile = "$acrylicHostsFile.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
        Copy-Item $acrylicHostsFile $backupFile -Force
        Write-Success "Fichier AcrylicHosts.txt sauvegardé : $backupFile"
        
        # Lire le contenu actuel
        $content = Get-Content $acrylicHostsFile -Raw
        
        # Vérifier si la règle existe déjà
        if ($content -match '127\.0\.0\.1.*\*\.localhost') {
            Write-Warning "La règle *.localhost existe déjà dans AcrylicHosts.txt"
        } else {
            # Lire les lignes
            $lines = Get-Content $acrylicHostsFile
            
            # Chercher la dernière ligne non commentée
            $lastNonCommentLine = -1
            for ($i = $lines.Length - 1; $i -ge 0; $i--) {
                $line = $lines[$i].Trim()
                if ($line -and -not $line.StartsWith('#')) {
                    $lastNonCommentLine = $i
                    break
                }
            }
            
            # Ajouter la règle après la dernière ligne non commentée
            if ($lastNonCommentLine -ge 0) {
                $newLines = @()
                $newLines += $lines[0..$lastNonCommentLine]
                $newLines += "127.0.0.1 *.localhost"
                if ($lastNonCommentLine -lt ($lines.Length - 1)) {
                    $newLines += $lines[($lastNonCommentLine + 1)..($lines.Length - 1)]
                }
            } else {
                # Si toutes les lignes sont des commentaires, ajouter à la fin
                $newLines = $lines + "127.0.0.1 *.localhost"
            }
            
            # Écrire le nouveau contenu avec les permissions appropriées
            $newContent = $newLines -join "`r`n"
            [System.IO.File]::WriteAllText($acrylicHostsFile, $newContent, [System.Text.Encoding]::UTF8)
            Write-Success "Règle *.localhost ajoutée à AcrylicHosts.txt"
            $needsRestart = $true
        }
        
        return @{ Success = $true; NeedsRestart = $needsRestart }
    } catch {
        Write-Error "Erreur lors de la modification d'AcrylicHosts.txt : $_"
        Write-Host "`nVeuillez modifier manuellement le fichier : $acrylicHostsFile" -ForegroundColor Yellow
        Write-Host "Ajoutez la ligne : 127.0.0.1 *.localhost" -ForegroundColor Yellow
        return @{ Success = $false; NeedsRestart = $false }
    }
}

function Set-WindowsDNS {
    Write-Step "Configuration des paramètres DNS Windows..."
    
    try {
        # Obtenir l'interface réseau active
        $adapter = Get-NetAdapter | Where-Object { $_.Status -eq "Up" -and $_.InterfaceDescription -notlike "*Loopback*" } | Select-Object -First 1
        
        if (-not $adapter) {
            Write-Error "Aucune interface réseau active trouvée"
            return $false
        }
        
        Write-Host "Interface réseau : $($adapter.Name)" -ForegroundColor Gray
        
        # Vérifier la configuration actuelle
        $currentDNS = Get-DnsClientServerAddress -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 | Select-Object -ExpandProperty ServerAddresses
        
        if ($currentDNS -contains "127.0.0.1") {
            Write-Warning "DNS déjà configuré pour utiliser 127.0.0.1"
        } else {
            # Configurer DNS
            Set-DnsClientServerAddress -InterfaceIndex $adapter.InterfaceIndex -ServerAddresses "127.0.0.1", "8.8.8.8"
            Write-Success "DNS Windows configuré : 127.0.0.1 (Acrylic), 8.8.8.8 (Google)"
        }
        
        return $true
    } catch {
        Write-Error "Erreur lors de la configuration DNS : $_"
        Write-Host "`nConfiguration manuelle requise :" -ForegroundColor Yellow
        Write-Host "1. Ouvrez les Paramètres réseau" -ForegroundColor Yellow
        Write-Host "2. Modifiez les options de la carte" -ForegroundColor Yellow
        Write-Host "3. Configurez DNS : 127.0.0.1 (préféré), 8.8.8.8 (auxiliaire)" -ForegroundColor Yellow
        return $false
    }
}

function Find-AcrylicService {
    # Chercher le service avec différents noms possibles
    $possibleNames = @("AcrylicService", "AcrylicDNSProxy", "Acrylic", "AcrylicHosts")
    
    foreach ($name in $possibleNames) {
        $service = Get-Service -Name $name -ErrorAction SilentlyContinue
        if ($service) {
            return $service
        }
    }
    
    # Chercher par nom d'affichage
    $service = Get-Service | Where-Object { $_.DisplayName -like "*Acrylic*" } | Select-Object -First 1
    if ($service) {
        return $service
    }
    
    return $null
}

function Install-AcrylicService {
    Write-Step "Tentative d'installation du service Acrylic..."
    
    $acrylicExe = Join-Path $acrylicPath "AcrylicService.exe"
    $acrylicUI = Join-Path $acrylicPath "AcrylicUI.exe"
    
    if (Test-Path $acrylicExe) {
        try {
            Write-Host "Installation du service via AcrylicService.exe..." -ForegroundColor Gray
            $process = Start-Process -FilePath $acrylicExe -ArgumentList "-install" -Wait -NoNewWindow -PassThru
            
            if ($process.ExitCode -eq 0) {
                Write-Success "Service installé avec succès"
                Start-Sleep -Seconds 3
                return $true
            } else {
                Write-Warning "L'installation a retourné le code : $($process.ExitCode)"
            }
        } catch {
            Write-Warning "Impossible d'installer le service via AcrylicService.exe : $_"
        }
    }
    
    # Si l'installation automatique échoue, donner des instructions
    Write-Host "`n⚠️  Installation manuelle requise :" -ForegroundColor Yellow
    Write-Host "`nOption 1 - Via la ligne de commande (recommandé) :" -ForegroundColor Cyan
    Write-Host "  & '$acrylicExe' -install" -ForegroundColor White
    Write-Host "`nOption 2 - Via l'interface graphique :" -ForegroundColor Cyan
    Write-Host "  1. Ouvrez : $acrylicUI" -ForegroundColor White
    Write-Host "  2. Cliquez sur 'Install Service' ou 'Installer le service'" -ForegroundColor White
    Write-Host "`nAprès l'installation, relancez ce script." -ForegroundColor Yellow
    
    # Proposer d'ouvrir l'interface
    $response = Read-Host "`nVoulez-vous ouvrir AcrylicUI maintenant ? (O/N)"
    if ($response -eq "O" -or $response -eq "o") {
        if (Test-Path $acrylicUI) {
            Start-Process $acrylicUI
            Write-Host "`nAcrylicUI ouvert. Installez le service, puis relancez ce script." -ForegroundColor Yellow
        }
    }
    
    return $false
}

function Start-AcrylicService {
    Write-Step "Démarrage du service Acrylic..."
    
    try {
        # Chercher le service avec différents noms
        $service = Find-AcrylicService
        
        if (-not $service) {
            Write-Warning "Service Acrylic introuvable. Tentative d'installation..."
            
            if (-not (Install-AcrylicService)) {
                Write-Error "Le service Acrylic n'est pas installé."
                Write-Host "`n📖 Consultez scripts\INSTALL_ACRYLIC_SERVICE.md pour les instructions détaillées" -ForegroundColor Yellow
                Write-Host "`nInstallation rapide :" -ForegroundColor Cyan
                Write-Host '  & "C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicService.exe" -install' -ForegroundColor White
                return $false
            }
            
            # Réessayer de trouver le service après installation
            Start-Sleep -Seconds 2
            $service = Find-AcrylicService
            
            if (-not $service) {
                Write-Error "Le service n'a pas pu être installé automatiquement."
                return $false
            }
        }
        
        $serviceName = $service.Name
        Write-Host "Service trouvé : $serviceName ($($service.DisplayName))" -ForegroundColor Gray
        
        if ($service.Status -eq "Running") {
            Write-Warning "Le service Acrylic est déjà démarré"
            try {
                # Utiliser sc.exe pour arrêter/démarrer le service (plus fiable)
                $null = sc.exe stop $serviceName
                Start-Sleep -Seconds 3
                $null = sc.exe start $serviceName
                Start-Sleep -Seconds 2
                Write-Success "Service Acrylic redémarré"
            } catch {
                Write-Warning "Impossible de redémarrer le service automatiquement : $_"
                Write-Host "Redémarrez manuellement via services.msc ou : sc stop $serviceName && sc start $serviceName" -ForegroundColor Yellow
            }
        } else {
            try {
                $null = sc.exe start $serviceName
                Start-Sleep -Seconds 2
                Write-Success "Service Acrylic démarré"
            } catch {
                Start-Service -Name $serviceName -ErrorAction SilentlyContinue
                Write-Success "Service Acrylic démarré"
            }
        }
        
        # Configurer le démarrage automatique
        Set-Service -Name $serviceName -StartupType Automatic
        Write-Success "Démarrage automatique activé"
        
        return $true
    } catch {
        Write-Error "Erreur lors du démarrage du service : $_"
        Write-Host "`nEssayez de démarrer le service manuellement :" -ForegroundColor Yellow
        Write-Host "1. Ouvrez services.msc" -ForegroundColor White
        Write-Host "2. Trouvez le service Acrylic" -ForegroundColor White
        Write-Host "3. Démarrez-le et configurez le démarrage automatique" -ForegroundColor White
        return $false
    }
}

function Test-AcrylicDNS {
    Write-Step "Test de la résolution DNS..."
    
    # Attendre un peu pour que le service soit prêt
    Start-Sleep -Seconds 2
    
    # Test avec nslookup (plus fiable)
    try {
        $nslookupResult = nslookup test.localhost 127.0.0.1 2>&1 | Out-String
        
        if ($nslookupResult -match "127\.0\.0\.1" -or $nslookupResult -match "Address:\s+127\.0\.0\.1") {
            Write-Success "✅ DNS fonctionne correctement ! test.localhost → 127.0.0.1"
            return $true
        }
        
        # Si nslookup échoue, essayer avec Resolve-DnsName
        try {
            $result = Resolve-DnsName -Name "test.localhost" -Server 127.0.0.1 -ErrorAction Stop
            if ($result.IPAddress -eq "127.0.0.1") {
                Write-Success "✅ DNS fonctionne correctement ! test.localhost → 127.0.0.1"
                return $true
            }
        } catch {
            # Ignorer cette erreur, on va diagnostiquer
        }
        
        # Diagnostic
        Write-Warning "La résolution DNS ne fonctionne pas correctement"
        Write-Host "`nDiagnostic :" -ForegroundColor Yellow
        
        # Vérifier la configuration Acrylic (AcrylicHosts.txt)
        $acrylicHostsFile = Join-Path $acrylicPath "AcrylicHosts.txt"
        if (Test-Path $acrylicHostsFile) {
            $hostsContent = Get-Content $acrylicHostsFile -Raw
            if ($hostsContent -match '127\.0\.0\.1.*\*\.localhost') {
                Write-Host "✓ La règle *.localhost est présente dans AcrylicHosts.txt" -ForegroundColor Green
            } else {
                Write-Host "✗ La règle *.localhost n'est pas trouvée dans AcrylicHosts.txt" -ForegroundColor Red
                Write-Host "  Vérifiez le fichier : $acrylicHostsFile" -ForegroundColor Yellow
            }
        } else {
            Write-Host "✗ Fichier AcrylicHosts.txt non trouvé" -ForegroundColor Red
        }
        
        # Vérifier que le service écoute
        $port53 = netstat -ano | findstr ":53" | findstr "LISTENING"
        if ($port53) {
            Write-Host "✓ Le service écoute sur le port 53" -ForegroundColor Green
        } else {
            Write-Host "✗ Le service n'écoute pas sur le port 53" -ForegroundColor Red
        }
        
        Write-Host "`nSolutions possibles :" -ForegroundColor Cyan
        Write-Host "1. Redémarrez le service Acrylic manuellement :" -ForegroundColor White
        $service = Find-AcrylicService
        if ($service) {
            Write-Host "   Stop-Service $($service.Name) -Force" -ForegroundColor Gray
            Write-Host "   Start-Service $($service.Name)" -ForegroundColor Gray
        }
        Write-Host "2. Vérifiez la configuration dans AcrylicUI.exe" -ForegroundColor White
        Write-Host "3. Exécutez le script de diagnostic : .\scripts\test-acrylic-dns.ps1" -ForegroundColor White
        Write-Host "4. Videz le cache DNS : ipconfig /flushdns" -ForegroundColor White
        Write-Host "5. Note : Acrylic peut nécessiter un redémarrage complet de Windows pour fonctionner correctement" -ForegroundColor Yellow
        
        return $false
    } catch {
        Write-Error "Erreur lors du test DNS : $_"
        return $false
    }
}

function Uninstall-AcrylicConfig {
    Write-Step "Désinstallation de la configuration Acrylic..."
    
    # Remettre DNS Windows en automatique
    try {
        $adapter = Get-NetAdapter | Where-Object { $_.Status -eq "Up" -and $_.InterfaceDescription -notlike "*Loopback*" } | Select-Object -First 1
        if ($adapter) {
            Set-DnsClientServerAddress -InterfaceIndex $adapter.InterfaceIndex -ResetServerAddresses
            Write-Success "DNS Windows remis en mode automatique"
        }
    } catch {
        Write-Warning "Impossible de réinitialiser DNS Windows : $_"
    }
    
    # Arrêter le service
    try {
        $service = Find-AcrylicService
        if ($service -and $service.Status -eq "Running") {
            Stop-Service -Name $service.Name
            Write-Success "Service Acrylic arrêté"
        }
    } catch {
        Write-Warning "Impossible d'arrêter le service : $_"
    }
    
    Write-Success "Configuration désinstallée"
}

# Script principal
Write-Host "`n" -NoNewline
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Configuration Acrylic DNS Proxy pour Laravel" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if ($Uninstall) {
    Uninstall-AcrylicConfig
    exit 0
}

# Vérifier l'installation
if (-not (Test-AcrylicInstalled)) {
    exit 1
}

# Configuration
$success = $true
$needsRestart = $false

$configResult = Install-AcrylicConfig
if ($configResult -is [hashtable]) {
    $needsRestart = $configResult.NeedsRestart
    $success = $configResult.Success -and $success
} else {
    $success = $configResult -and $success
}

$success = Set-WindowsDNS -and $success
$serviceResult = Start-AcrylicService
$success = $serviceResult -and $success

# Si la configuration a été modifiée, redémarrer le service
if ($needsRestart -and $serviceResult) {
    Write-Step "Redémarrage du service pour appliquer la nouvelle configuration..."
    $service = Find-AcrylicService
    if ($service) {
        try {
            # Utiliser sc.exe pour redémarrer (plus fiable)
            $null = sc.exe stop $service.Name
            Start-Sleep -Seconds 3
            $null = sc.exe start $service.Name
            Start-Sleep -Seconds 3
            Write-Success "Service redémarré pour appliquer la nouvelle configuration"
        } catch {
            Write-Warning "Impossible de redémarrer le service automatiquement : $_"
            Write-Host "Veuillez redémarrer le service manuellement :" -ForegroundColor Yellow
            Write-Host "  sc stop $($service.Name)" -ForegroundColor Gray
            Write-Host "  sc start $($service.Name)" -ForegroundColor Gray
            Write-Host "Ou via services.msc" -ForegroundColor Gray
        }
    }
}

# Attendre un peu pour que le service soit prêt
Start-Sleep -Seconds 2

# Vider le cache DNS
Write-Step "Vidage du cache DNS..."
ipconfig /flushdns | Out-Null
Write-Success "Cache DNS vidé"

# Test
$dnsTest = Test-AcrylicDNS

# Résumé
Write-Host "`n" -NoNewline
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  Résumé" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if ($success -and $dnsTest) {
    Write-Success "Configuration terminée avec succès !"
    Write-Host "`nProchaines étapes :" -ForegroundColor Yellow
    Write-Host "1. Démarrez votre serveur Laravel : php artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor White
    Write-Host "2. Testez l'onboarding et vérifiez la redirection vers *.localhost:8000" -ForegroundColor White
} else {
    Write-Error "La configuration n'est pas complète. Vérifiez les erreurs ci-dessus."
    Write-Host "`nPour désinstaller, exécutez : .\setup-acrylic.ps1 -Uninstall" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

