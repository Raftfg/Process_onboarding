<?php
// Script simple pour déclencher un webhook de test
$apiKey = 'ak_i9qv0FUrRGx0sBTH4CVqQGHVnVQzdDL28XYZlyzdgwkOXDuh';
$testUrl = 'http://localhost:8000/api/webhooks/test';

echo "=== DÉCLENCHEMENT DU WEBHOOK DE TEST ===\n\n";

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP : $httpCode\n";
echo "Réponse API : $response\n\n";
echo "Vérifiez maintenant le terminal où tourne 'php -S localhost:9000 webhook_demo_receiver.php'\n";
echo "Vous devriez voir le webhook reçu avec la vérification HMAC réussie !\n";
