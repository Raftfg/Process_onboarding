# Script PowerShell de test manuel pour le microservice d'onboarding
# Usage: .\test_script.ps1 -MasterKey "mk_abc123..."

param(
    [Parameter(Mandatory=$true)]
    [string]$MasterKey
)

$BaseUrl = "http://127.0.0.1:8000"
$Timestamp = Get-Date -Format "yyyyMMddHHmmss"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Tests Manuels - Microservice Onboarding" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Test 1: Créer un onboarding
Write-Host "Test 1: Création d'onboarding" -ForegroundColor Yellow
Write-Host "----------------------------" -ForegroundColor Yellow

$body = @{
    email = "test-manual-$Timestamp@example.com"
    organization_name = "Test Manuel $Timestamp"
} | ConvertTo-Json

$headers = @{
    "X-Master-Key" = $MasterKey
    "Content-Type" = "application/json"
}

try {
    $response = Invoke-RestMethod -Uri "$BaseUrl/api/v1/onboarding/start" `
        -Method Post `
        -Headers $headers `
        -Body $body
    
    $uuid = $response.uuid
    Write-Host "✅ Onboarding créé: $uuid" -ForegroundColor Green
    Write-Host "   Subdomain: $($response.subdomain)" -ForegroundColor Gray
    Write-Host "   Status: $($response.onboarding_status)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Échec de création: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Test 2: Vérifier le statut
Write-Host "Test 2: Vérification du statut" -ForegroundColor Yellow
Write-Host "-------------------------------" -ForegroundColor Yellow

try {
    $statusResponse = Invoke-RestMethod -Uri "$BaseUrl/api/v1/onboarding/status/$uuid" `
        -Method Get `
        -Headers $headers
    
    if ($statusResponse.onboarding_status -eq "pending") {
        Write-Host "✅ Statut correct: $($statusResponse.onboarding_status)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Statut: $($statusResponse.onboarding_status)" -ForegroundColor Yellow
    }
    
    Write-Host "   Metadata présentes: $($statusResponse.metadata -ne $null)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 3: Provisioning
Write-Host "Test 3: Provisioning" -ForegroundColor Yellow
Write-Host "--------------------" -ForegroundColor Yellow

$provisionBody = @{
    uuid = $uuid
    generate_api_key = $true
} | ConvertTo-Json

try {
    $provisionResponse = Invoke-RestMethod -Uri "$BaseUrl/api/v1/onboarding/provision" `
        -Method Post `
        -Headers $headers `
        -Body $provisionBody
    
    if ($provisionResponse.onboarding_status -eq "activated") {
        Write-Host "✅ Provisioning réussi: $($provisionResponse.onboarding_status)" -ForegroundColor Green
        Write-Host "   API Key générée: $($provisionResponse.api_key -ne $null)" -ForegroundColor Gray
        Write-Host "   Infrastructure Status: $($provisionResponse.metadata.infrastructure_status)" -ForegroundColor Gray
        Write-Host "   Tentatives: $($provisionResponse.metadata.provisioning_attempts)" -ForegroundColor Gray
    } else {
        Write-Host "⚠️  Provisioning: $($provisionResponse.onboarding_status)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 4: Test d'idempotence
Write-Host "Test 4: Test d'idempotence" -ForegroundColor Yellow
Write-Host "--------------------------" -ForegroundColor Yellow

$idempotentBody = @{
    uuid = $uuid
} | ConvertTo-Json

try {
    $idempotentResponse = Invoke-RestMethod -Uri "$BaseUrl/api/v1/onboarding/provision" `
        -Method Post `
        -Headers $headers `
        -Body $idempotentBody
    
    $isIdempotent = $idempotentResponse.metadata.is_idempotent
    $apiKey = $idempotentResponse.api_key
    
    if ($isIdempotent -eq $true -and $apiKey -eq $null) {
        Write-Host "✅ Idempotence fonctionne correctement" -ForegroundColor Green
        Write-Host "   is_idempotent: $isIdempotent" -ForegroundColor Gray
        Write-Host "   api_key: null (non régénéré)" -ForegroundColor Gray
    } else {
        Write-Host "⚠️  Idempotence: is_idempotent=$isIdempotent, api_key=$apiKey" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 5: Vérifier les metadata finales
Write-Host "Test 5: Vérification des metadata finales" -ForegroundColor Yellow
Write-Host "------------------------------------------" -ForegroundColor Yellow

try {
    $finalStatus = Invoke-RestMethod -Uri "$BaseUrl/api/v1/onboarding/status/$uuid" `
        -Method Get `
        -Headers $headers
    
    Write-Host "✅ Metadata complètes:" -ForegroundColor Green
    Write-Host "   Infrastructure Status: $($finalStatus.metadata.infrastructure_status)" -ForegroundColor Gray
    Write-Host "   DNS Configuré: $($finalStatus.metadata.dns_configured)" -ForegroundColor Gray
    Write-Host "   SSL Configuré: $($finalStatus.metadata.ssl_configured)" -ForegroundColor Gray
    Write-Host "   Tentatives: $($finalStatus.metadata.provisioning_attempts)" -ForegroundColor Gray
    Write-Host "   API Key générée: $($finalStatus.metadata.api_key_generated)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Erreur: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Tests terminés" -ForegroundColor Cyan
Write-Host "UUID de test: $uuid" -ForegroundColor Gray
Write-Host "==========================================" -ForegroundColor Cyan
