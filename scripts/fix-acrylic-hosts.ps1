# Script rapide pour ajouter la règle *.localhost dans AcrylicHosts.txt
# Exécutez en tant qu'administrateur

$acrylicPath = "C:\Program Files (x86)\Acrylic DNS Proxy"
$acrylicHostsFile = Join-Path $acrylicPath "AcrylicHosts.txt"

if (-not (Test-Path $acrylicHostsFile)) {
    Write-Host "Fichier AcrylicHosts.txt introuvable : $acrylicHostsFile" -ForegroundColor Red
    exit 1
}

# Lire le contenu
$content = Get-Content $acrylicHostsFile -Raw

# Vérifier si la règle existe déjà
if ($content -match '127\.0\.0\.1.*\*\.localhost') {
    Write-Host "La règle *.localhost existe déjà" -ForegroundColor Yellow
} else {
    # Ajouter la règle
    $lines = Get-Content $acrylicHostsFile
    $newLines = $lines + "127.0.0.1 *.localhost"
    $newContent = $newLines -join "`r`n"
    
    # Écrire avec les permissions appropriées
    [System.IO.File]::WriteAllText($acrylicHostsFile, $newContent, [System.Text.Encoding]::UTF8)
    Write-Host "Règle *.localhost ajoutée à AcrylicHosts.txt" -ForegroundColor Green
}

# Redémarrer le service
$service = Get-Service | Where-Object { $_.DisplayName -like "*Acrylic*" } | Select-Object -First 1
if ($service) {
    Write-Host "Redémarrage du service Acrylic..." -ForegroundColor Cyan
    sc.exe stop $service.Name
    Start-Sleep -Seconds 2
    sc.exe start $service.Name
    Start-Sleep -Seconds 3
    Write-Host "Service redémarré" -ForegroundColor Green
}

# Tester
Write-Host "`nTest de résolution DNS..." -ForegroundColor Cyan
$result = nslookup test.localhost 127.0.0.1 2>&1 | Out-String
if ($result -match "127\.0\.0\.1") {
    Write-Host "DNS fonctionne ! test.localhost -> 127.0.0.1" -ForegroundColor Green
} else {
    Write-Host "DNS ne fonctionne pas encore" -ForegroundColor Red
    Write-Host "Reponse : $result" -ForegroundColor Gray
}

