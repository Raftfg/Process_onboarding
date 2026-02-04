<?php

/**
 * SIMULATION D'UNE APPLICATION CLIENTE (ex: Votre projet Vue/Laravel)
 * 
 * Ce script simule ce qui se passe dans le Controller Laravel de votre AUTRE projet.
 * Il envoie une requête au Microservice Onboarding (localhost:8000)
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// --- CONFIGURATION CLIENT ---
// Ces valeurs viendraient de votre .env dans l'autre projet
$microserviceUrl = 'http://localhost:8000';
$apiKey = 'VOTRE_CLE_ICI'; // Remplacez par une clé générée dans le dashboard admin
$appName = 'Mon-Projet-Vue-Laravel'; // Doit correspondre à l'App Name de la clé
// ----------------------------

echo "--- DEBUT SIMULATION CLIENT ---\n";
echo "1. Préparation de la requête depuis le Backend Client...\n";

// Données reçues du formulaire Vue.js
$formData = [
    'organization_name' => 'Hopital Local Test ' . rand(100, 999),
    'email' => 'admin@hopital-test-' . rand(100, 999) . '.com',
    'metadata' => [
        'source' => 'Formulaire Inscription V2',
        'plan_choisi' => 'Premium'
    ]
];

try {
    $client = new Client();

    echo "2. Envoi de la requête au Microservice ($microserviceUrl)...\n";
    echo "   Headers: X-API-Key et X-App-Name inclus.\n";

    $response = $client->post("$microserviceUrl/api/v1/onboarding/external", [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-Key' => $apiKey,
            'X-App-Name' => $appName, // TRES IMPORTANT
        ],
        'json' => $formData
    ]);

    $result = json_decode($response->getBody(), true);

    echo "\n3. RÉPONSE D'ONBOARDING (Succès) :\n";
    echo "------------------------------------------------\n";
    echo "Message : " . $result['message'] . "\n";
    echo "URL Tenant : " . $result['result']['url'] . "\n";
    echo "Token Admin : " . $result['result']['activation_token'] . "\n";
    echo "------------------------------------------------\n";
    echo "\nACTION: Rediriger l'utilisateur Vue.js vers : " . $result['result']['url'] . "\n";

} catch (RequestException $e) {
    echo "\n3. ERREUR LORS DE L'APPEL :\n";
    echo "------------------------------------------------\n";
    if ($e->hasResponse()) {
        echo "Status Code : " . $e->getResponse()->getStatusCode() . "\n";
        echo "Erreur : " . $e->getResponse()->getBody() . "\n";
    } else {
        echo "Erreur de connexion : " . $e->getMessage() . "\n";
    }
    echo "------------------------------------------------\n";
    print_r($formData);
}
