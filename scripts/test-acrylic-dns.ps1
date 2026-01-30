# Script de test pour diagnostiquer Acrylic DNS Proxy
# Exécutez en tant qu'administrateur

Write-Host "`n=== Diagnostic Acrylic DNS Proxy ===" -ForegroundColor Cyan
Write-Host ""

# 1. Vérifier le service
Write-Host "1. Vérification du service..." -ForegroundColor Yellow
$service = Get-Service | Where-Object { $_.DisplayName -like "*Acrylic*" } | Select-Object -First 1
if ($service) {
    Write-Host "   ✓ Service trouvé : $($service.Name) - Statut : $($service.Status)" -ForegroundColor Green
} else {
    Write-Host "   ✗ Service non trouvé" -ForegroundColor Red
    exit 1
}

# 2. Vérifier le port 53
Write-Host "`n2. Vérification du port 53..." -ForegroundColor Yellow
$port53 = netstat -ano | findstr ":53" | findstr "LISTENING"
if ($port53) {
    Write-Host "   ✓ Le service écoute sur le port 53" -ForegroundColor Green
    Write-Host "   $port53" -ForegroundColor Gray
} else {
    Write-Host "   ✗ Le service n'écoute pas sur le port 53" -ForegroundColor Red
}

# 3. Vérifier la configuration
Write-Host "`n3. Vérification de la configuration..." -ForegroundColor Yellow
$configFile = "C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicConfiguration.ini"
if (Test-Path $configFile) {
    $config = Get-Content $configFile -Raw
    if ($config -match '127\.0\.0\.1.*\*\.localhost') {
        Write-Host "   ✓ La règle *.localhost est présente" -ForegroundColor Green
        Write-Host "   Règle trouvée :" -ForegroundColor Gray
        $config | Select-String -Pattern "127\.0\.0\.1.*localhost" | ForEach-Object { Write-Host "   $_" -ForegroundColor Gray }
    } else {
        Write-Host "   ✗ La règle *.localhost n'est pas trouvée" -ForegroundColor Red
    }
} else {
    Write-Host "   ✗ Fichier de configuration non trouvé" -ForegroundColor Red
}

# 4. Tester avec différents domaines
Write-Host "`n4. Tests de résolution DNS..." -ForegroundColor Yellow

$testDomains = @(
    "test.localhost",
    "example.localhost",
    "localhost"
)

foreach ($domain in $testDomains) {
    Write-Host "   Test : $domain" -ForegroundColor Gray
    $result = nslookup $domain 127.0.0.1 2>&1 | Out-String
    if ($result -match "127\.0\.0\.1" -or $result -match "Address:\s+127\.0\.0\.1") {
        Write-Host "   ✓ $domain → 127.0.0.1" -ForegroundColor Green
    } else {
        Write-Host "   ✗ $domain : Échec" -ForegroundColor Red
        Write-Host "   Réponse : $($result.Trim())" -ForegroundColor DarkGray
    }
}

# 5. Vérifier DNS Windows
Write-Host "`n5. Vérification DNS Windows..." -ForegroundColor Yellow
$adapter = Get-NetAdapter | Where-Object { $_.Status -eq "Up" -and $_.InterfaceDescription -notlike "*Loopback*" } | Select-Object -First 1
if ($adapter) {
    $dns = Get-DnsClientServerAddress -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 | Select-Object -ExpandProperty ServerAddresses
    if ($dns -contains "127.0.0.1") {
        Write-Host "   ✓ DNS Windows configuré pour utiliser 127.0.0.1" -ForegroundColor Green
        Write-Host "   Serveurs DNS : $($dns -join ', ')" -ForegroundColor Gray
    } else {
        Write-Host "   ✗ DNS Windows n'utilise pas 127.0.0.1" -ForegroundColor Red
        Write-Host "   Serveurs DNS actuels : $($dns -join ', ')" -ForegroundColor Gray
    }
}

# 6. Suggestions
Write-Host "`n=== Suggestions ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Si les tests échouent, essayez :" -ForegroundColor Yellow
Write-Host "1. Redémarrer le service : Restart-Service $($service.Name)" -ForegroundColor White
Write-Host "2. Vider le cache DNS : ipconfig /flushdns" -ForegroundColor White
Write-Host "3. Vérifier AcrylicUI.exe pour voir les logs" -ForegroundColor White
Write-Host "4. Essayer avec un sous-domaine spécifique dans le fichier hosts" -ForegroundColor White
Write-Host ""

