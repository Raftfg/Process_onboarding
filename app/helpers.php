<?php

if (!function_exists('subdomain_url')) {
    /**
     * Génère une URL avec le sous-domaine dans l'URL plutôt qu'en paramètre
     * 
     * @param string $subdomain Le sous-domaine
     * @param string $path Le chemin (ex: '/dashboard', '/welcome')
     * @param array $queryParams Paramètres de requête additionnels
     * @return string L'URL complète avec sous-domaine
     */
    function subdomain_url(string $subdomain, string $path = '/', array $queryParams = []): string
    {
        // En développement local, utiliser le sous-domaine dans l'URL
        if (config('app.env') === 'local') {
            $baseUrl = config('app.url', 'http://127.0.0.1:8000');
            
            // Extraire le host et le port
            $parsedUrl = parse_url($baseUrl);
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $host = $parsedUrl['host'] ?? '127.0.0.1';
            $port = $parsedUrl['port'] ?? 8000;
            
            // Construire l'URL avec le sous-domaine
            $subdomainHost = "{$subdomain}.localhost";
            $url = "{$scheme}://{$subdomainHost}:{$port}{$path}";
        } else {
            // En production, utiliser le vrai sous-domaine
            $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
            $scheme = config('app.force_https', false) ? 'https' : 'http';
            $url = "{$scheme}://{$subdomain}.{$baseDomain}{$path}";
        }
        
        // Ajouter les paramètres de requête s'il y en a
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        return $url;
    }
}

