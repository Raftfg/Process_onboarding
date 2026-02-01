<?php
/**
 * Script de test pour le Microservice d'Onboarding Akasi Group
 * Ce script démontre comment intégrer le service via l'API REST.
 */

$apiKey = 'ak_xKjfmx5jss9zUG8drBRnVtC7VcxDZNZ9pH7b4KyKc3E7Z0rW';
$baseUrl = 'http://127.0.0.1:8000/api';

echo "--- DÉBUT DU TEST API MICROSERVICE ---\n\n";

// 1. Définition du payload générique (Agnostique du domaine)
$payload = [
    'organization' => [
        'name' => 'Ma Super Org ' . rand(100, 999),
        'address' => '123 Rue de la Tech, Paris',
        'phone' => '+33 1 23 45 67 89',
        'email' => 'contact@ma-super-org.com'
    ],
    'admin' => [
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'admin@ma-super-org.com',
        'password' => 'SecretPass123!'
    ]
];

echo "1. Envoi de la requête de création d'onboarding (POST /onboarding/create)...\n";

$ch = curl_init($baseUrl . '/onboarding/create');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 && $httpCode !== 201) {
    echo "ERREUR : Code HTTP $httpCode\n";
    echo "Réponse : $response\n";
    exit(1);
}

$data = json_decode($response, true);
echo "SUCCÈS ! Réponse reçue :\n";
print_r($data);

$subdomain = $data['data']['subdomain'] ?? null;

if (!$subdomain) {
    echo "ERREUR : Aucun sous-domaine reçu.\n";
    exit(1);
}

echo "\n2. Vérification du statut de l'onboarding pour '$subdomain'...\n";

$ch = curl_init($baseUrl . "/onboarding/status/$subdomain");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$statusData = json_decode($response, true);
curl_close($ch);

echo "Statut actuel : " . ($statusData['data']['status'] ?? 'Inconnu') . "\n";
echo "URL d'accès : " . ($statusData['data']['url'] ?? 'N/A') . "\n";

echo "\n--- TEST TERMINÉ AVEC SUCCÈS ---\n";
