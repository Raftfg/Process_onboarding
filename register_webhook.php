<?php
// Script pour enregistrer le webhook et tester
$apiKey = 'ak_i9qv0FUrRGx0sBTH4CVqQGHVnVQzdDL28XYZlyzdgwkOXDuh';
$registerUrl = 'http://localhost:8000/api/webhooks/register';
$testUrl = 'http://localhost:8000/api/webhooks/test';

// 1. Enregistrer le Webhook
echo "--- Enregistrement du Webhook ---\n";
$registerData = [
    'url' => 'http://localhost:9000',
    'events' => ['test', 'onboarding.completed'],
    'secret' => 'akasigroup_test_secret_789'
];

$ch = curl_init($registerUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

echo "Réponse Enregistrement : " . $response . "\n\n";

// 2. Déclencher le test
echo "--- Déclenchement du Test ---\n";
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

echo "Réponse Test : " . $response . "\n";
