<?php

/**
 * Exemple d'intégration du microservice d'onboarding MedKey
 * 
 * Ce fichier montre comment intégrer le microservice dans votre application PHP
 */

class MedKeyOnboardingClient
{
    private $apiUrl;
    private $apiKey;

    public function __construct($apiUrl, $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Créer un nouveau tenant via l'API
     * 
     * @param array $hospitalData Données de l'hôpital
     * @param array $adminData Données de l'administrateur
     * @param array $options Options supplémentaires
     * @return array Résultat de l'onboarding
     * @throws Exception
     */
    public function createOnboarding(array $hospitalData, array $adminData, array $options = [])
    {
        $data = [
            'hospital' => [
                'name' => $hospitalData['name'],
                'address' => $hospitalData['address'] ?? null,
                'phone' => $hospitalData['phone'] ?? null,
                'email' => $hospitalData['email'] ?? null,
            ],
            'admin' => [
                'first_name' => $adminData['first_name'],
                'last_name' => $adminData['last_name'],
                'email' => $adminData['email'],
                'password' => $adminData['password'],
            ],
            'options' => [
                'send_welcome_email' => $options['send_welcome_email'] ?? true,
                'auto_login' => $options['auto_login'] ?? true,
            ]
        ];

        $response = $this->makeRequest('POST', '/onboarding/create', $data);

        if (!$response['success']) {
            throw new Exception($response['message'] ?? 'Erreur lors de la création de l\'onboarding');
        }

        return $response['data'];
    }

    /**
     * Vérifier le statut d'un onboarding
     * 
     * @param string $subdomain Le sous-domaine du tenant
     * @return array Statut de l'onboarding
     * @throws Exception
     */
    public function getOnboardingStatus(string $subdomain)
    {
        $response = $this->makeRequest('GET', "/onboarding/status/{$subdomain}");

        if (!$response['success']) {
            throw new Exception($response['message'] ?? 'Erreur lors de la récupération du statut');
        }

        return $response['data'];
    }

    /**
     * Obtenir les informations d'un tenant
     * 
     * @param string $subdomain Le sous-domaine du tenant
     * @return array Informations du tenant
     * @throws Exception
     */
    public function getTenantInfo(string $subdomain)
    {
        $response = $this->makeRequest('GET', "/tenant/{$subdomain}");

        if (!$response['success']) {
            throw new Exception($response['message'] ?? 'Erreur lors de la récupération du tenant');
        }

        return $response['data'];
    }

    /**
     * Effectuer une requête HTTP
     * 
     * @param string $method Méthode HTTP (GET, POST, etc.)
     * @param string $endpoint Endpoint de l'API
     * @param array|null $data Données à envoyer (pour POST)
     * @return array Réponse de l'API
     * @throws Exception
     */
    private function makeRequest(string $method, string $endpoint, array $data = null)
    {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erreur cURL: {$error}");
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }
}

// Exemple d'utilisation
try {
    // Initialiser le client
    $client = new MedKeyOnboardingClient(
        'https://onboarding.medkey.com/api',
        'YOUR_API_KEY_HERE'
    );

    // Créer un nouveau tenant
    $result = $client->createOnboarding(
        [
            'name' => 'Hôpital Central',
            'address' => '123 Rue de la Santé, Paris',
            'phone' => '+33 1 23 45 67 89',
            'email' => 'contact@hopital-central.fr'
        ],
        [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'admin@hopital-central.fr',
            'password' => 'SecurePassword123!'
        ],
        [
            'send_welcome_email' => true,
            'auto_login' => true
        ]
    );

    echo "Onboarding créé avec succès!\n";
    echo "Subdomain: " . $result['subdomain'] . "\n";
    echo "URL: " . $result['url'] . "\n";
    echo "Database: " . $result['database_name'] . "\n";

    // Vérifier le statut
    $status = $client->getOnboardingStatus($result['subdomain']);
    echo "Statut: " . $status['status'] . "\n";

    // Obtenir les informations complètes
    $tenantInfo = $client->getTenantInfo($result['subdomain']);
    echo "Hôpital: " . $tenantInfo['hospital_name'] . "\n";

} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
