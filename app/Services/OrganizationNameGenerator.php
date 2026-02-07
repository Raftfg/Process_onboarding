<?php

namespace App\Services;

class OrganizationNameGenerator
{
    /**
     * Génère un nom d'organisation à partir d'un email
     * 
     * @param string $email
     * @return string Ex: "User-admin" depuis "admin@example.com"
     */
    public function generateFromEmail(string $email): string
    {
        // Extraire la partie locale de l'email (avant @)
        $localPart = explode('@', $email)[0];
        
        // Nettoyer et formater
        $localPart = preg_replace('/[^a-zA-Z0-9]/', '', $localPart);
        
        return "User-{$localPart}";
    }

    /**
     * Génère un nom d'organisation à partir d'un timestamp
     * 
     * @return string Ex: "Tenant-1738501234"
     */
    public function generateFromTimestamp(): string
    {
        $timestamp = time();
        return "Tenant-{$timestamp}";
    }

    /**
     * Génère un nom d'organisation à partir des métadonnées
     * 
     * @param array $metadata
     * @param string|null $fieldName Nom du champ à utiliser (défaut: 'name' ou 'organization_name')
     * @return string|null Retourne null si aucun champ valide trouvé
     */
    public function generateFromMetadata(array $metadata, ?string $fieldName = null): ?string
    {
        // Si un champ spécifique est demandé
        if ($fieldName && isset($metadata[$fieldName])) {
            return (string) $metadata[$fieldName];
        }

        // Essayer plusieurs champs courants
        $possibleFields = ['name', 'organization_name', 'company_name', 'tenant_name', 'client_name'];
        
        foreach ($possibleFields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
                return (string) $metadata[$field];
            }
        }

        return null;
    }

    /**
     * Génère un nom d'organisation à partir d'un template personnalisé
     * 
     * @param string $template Template avec placeholders (ex: "Tenant-{timestamp}" ou "User-{email}")
     * @param array $data Données pour remplacer les placeholders
     * @return string
     */
    public function generateFromTemplate(string $template, array $data = []): string
    {
        $result = $template;

        // Remplacer les placeholders
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $result = str_replace($placeholder, $value, $result);
        }

        // Placeholders spéciaux disponibles
        $specialPlaceholders = [
            '{timestamp}' => time(),
            '{date}' => date('Y-m-d'),
            '{datetime}' => date('Y-m-d-H-i-s'),
            '{random}' => substr(md5(uniqid()), 0, 8),
        ];

        foreach ($specialPlaceholders as $placeholder => $value) {
            $result = str_replace($placeholder, $value, $result);
        }

        // Si l'email est fourni, extraire la partie locale
        if (isset($data['email'])) {
            $emailLocal = explode('@', $data['email'])[0];
            $emailLocal = preg_replace('/[^a-zA-Z0-9]/', '', $emailLocal);
            $result = str_replace('{email}', $emailLocal, $result);
            $result = str_replace('{email_local}', $emailLocal, $result);
        }

        return $result;
    }

    /**
     * Génère un nom d'organisation selon une stratégie
     * 
     * @param string $strategy Stratégie: 'auto', 'email', 'timestamp', 'metadata', 'custom'
     * @param array $context Données contextuelles (email, metadata, template, etc.)
     * @return string
     */
    public function generate(string $strategy, array $context = []): string
    {
        switch ($strategy) {
            case 'email':
                if (!isset($context['email'])) {
                    throw new \InvalidArgumentException('Email is required for email strategy');
                }
                return $this->generateFromEmail($context['email']);

            case 'timestamp':
                return $this->generateFromTimestamp();

            case 'metadata':
                $fieldName = $context['metadata_field'] ?? null;
                $metadata = $context['metadata'] ?? [];
                $generated = $this->generateFromMetadata($metadata, $fieldName);
                if ($generated === null) {
                    // Fallback sur timestamp si metadata vide
                    return $this->generateFromTimestamp();
                }
                return $generated;

            case 'custom':
                $template = $context['template'] ?? 'Tenant-{timestamp}';
                return $this->generateFromTemplate($template, $context);

            case 'auto':
            default:
                // Stratégie auto : essayer metadata d'abord, puis email, puis timestamp
                if (isset($context['metadata']) && !empty($context['metadata'])) {
                    $generated = $this->generateFromMetadata($context['metadata']);
                    if ($generated !== null) {
                        return $generated;
                    }
                }
                
                if (isset($context['email'])) {
                    return $this->generateFromEmail($context['email']);
                }
                
                return $this->generateFromTimestamp();
        }
    }
}
