<?php
/**
 * DÉMONSTRATION : RÉCEPTEUR DE WEBHOOK (MICROSERVICE ONBOARDING)
 * 
 * Ce script simule votre application parente (CRM, ERP) qui reçoit 
 * des notifications du microservice Akasi Group.
 */

// --- CONFIGURATION ---
// Ce secret doit correspondre à celui retourné lors de l'enregistrement du webhook
$webhookSecret = 'inxA0EUZO2FT55R6CUw3vJPqCPZx9UoU'; 

// --- LOGIQUE DE RÉCEPTION ---
$payload = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-Webhook-Signature'] ?? '';

echo "--- WEBHOOK REÇU ---\n";
echo "Date : " . date('Y-m-d H:i:s') . "\n";
echo "Signature reçue : $signature\n";

if (!$payload) {
    echo "ERREUR : Aucun payload reçu.\n";
    http_response_code(400);
    exit;
}

// --- VÉRIFICATION DE LA SIGNATURE (SÉCURITÉ) ---
// La signature est un HMAC-SHA256 du JSON brut avec votre secret
$computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

echo "Signature calculée : $computedSignature\n";

if (hash_equals($signature, $computedSignature)) {
    echo "VÉRIFICATION RÉUSSIE : L'expéditeur est authentique.\n";
    
    $data = json_decode($payload, true);
    $event = $data['event'] ?? 'inconnu';
    
    echo "Événement : $event\n";
    echo "Données du Tenant :\n";
    print_r($data['data']);
    
    // ICI : Traitez l'événement (ex: débloquer des accès, envoyer un cadeau, etc.)
    
    http_response_code(200);
} else {
    echo "ÉCHEC DE VÉRIFICATION : Signature invalide !\n";
    http_response_code(401); // Unauthorized
}

echo "--- FIN DU TRAITEMENT ---\n";
