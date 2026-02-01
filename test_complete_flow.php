<?php
/**
 * TEST COMPLET : Onboarding + Webhook avec Métadonnées
 * 
 * Ce script démontre le cycle complet :
 * 1. Création d'un tenant via API avec métadonnées personnalisées
 * 2. Réception automatique du webhook avec les métadonnées
 */

$apiKey = 'ak_i9qv0FUrRGx0sBTH4CVqQGHVnVQzdDL28XYZlyzdgwkOXDuh';
$onboardingUrl = 'http://localhost:8000/api/onboarding/create';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  TEST COMPLET : ONBOARDING + WEBHOOK + MÉTADONNÉES        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Données de test avec métadonnées personnalisées
$testData = [
    'organization' => [
        'name' => 'Café Test Webhook ' . date('His'),
        'email' => 'webhook-test-' . time() . '@cafe-demo.fr'
    ],
    'metadata' => [
        'crm_client_id' => 'CRM-' . rand(1000, 9999),
        'plan_type' => 'premium',
        'referred_by' => 'Lucas',
        'custom_field' => 'Valeur personnalisée pour démonstration'
    ]
];

echo "📤 Envoi de la requête d'onboarding...\n";
echo "   Organisation : " . $testData['organization']['name'] . "\n";
echo "   Email : " . $testData['organization']['email'] . "\n";
echo "   Métadonnées : " . json_encode($testData['metadata'], JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($onboardingUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📥 Réponse de l'API (Code HTTP: $httpCode)\n";
echo str_repeat('─', 60) . "\n";

if ($httpCode === 201) {
    $result = json_decode($response, true);
    echo "✅ SUCCÈS ! Tenant créé avec succès\n\n";
    echo "Détails du tenant :\n";
    echo "  • Sous-domaine : " . ($result['data']['subdomain'] ?? 'N/A') . "\n";
    echo "  • URL : " . ($result['data']['url'] ?? 'N/A') . "\n";
    echo "  • Email admin : " . ($result['data']['admin_email'] ?? 'N/A') . "\n";
    echo "  • Base de données : " . ($result['data']['database_name'] ?? 'N/A') . "\n\n";
    
    echo "🔔 VÉRIFIEZ MAINTENANT LE TERMINAL DU WEBHOOK RECEIVER !\n";
    echo "   Vous devriez voir :\n";
    echo "   1. Un webhook 'onboarding.completed' reçu\n";
    echo "   2. La vérification HMAC réussie\n";
    echo "   3. VOS MÉTADONNÉES dans le payload :\n";
    echo "      - crm_client_id: " . $testData['metadata']['crm_client_id'] . "\n";
    echo "      - plan_type: " . $testData['metadata']['plan_type'] . "\n";
    echo "      - referred_by: " . $testData['metadata']['referred_by'] . "\n\n";
    
    echo "💡 C'est exactement ce dont vos collègues ont besoin pour synchroniser\n";
    echo "   leurs systèmes externes avec le microservice !\n";
} else {
    echo "❌ ERREUR (Code $httpCode)\n";
    echo $response . "\n";
}

echo "\n" . str_repeat('═', 60) . "\n";
