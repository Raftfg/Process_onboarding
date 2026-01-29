# Configuration des Sous-domaines

Ce document explique comment configurer les sous-domaines pour le système d'onboarding MedKey.

## Configuration Apache

Pour chaque sous-domaine créé, vous devez créer un fichier de configuration Apache.

### Exemple de configuration Apache (vhost)

Créez un fichier dans `/etc/apache2/sites-available/` (Linux) ou dans votre répertoire de configuration Apache :

```apache
<VirtualHost *:80>
    ServerName subdomain.medkey.local
    DocumentRoot /var/www/html/public
    
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/subdomain_error.log
    CustomLog ${APACHE_LOG_DIR}/subdomain_access.log combined
</VirtualHost>
```

Activez le site :
```bash
sudo a2ensite subdomain.conf
sudo systemctl reload apache2
```

## Configuration Nginx

Pour Nginx, créez un fichier de configuration :

```nginx
server {
    listen 80;
    server_name subdomain.medkey.local;
    root /var/www/html/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Configuration DNS

### Pour le développement local

Ajoutez dans votre fichier `/etc/hosts` (Linux/Mac) ou `C:\Windows\System32\drivers\etc\hosts` (Windows) :

```
127.0.0.1 subdomain.medkey.local
```

### Pour la production

Configurez votre DNS pour pointer vers votre serveur :
- Type A : `subdomain.medkey.com` → IP du serveur
- Ou utilisez un wildcard : `*.medkey.com` → IP du serveur

## Automatisation

Pour automatiser la création des sous-domaines, vous pouvez modifier la méthode `createSubdomain()` dans `app/Services/OnboardingService.php` pour :

1. Créer le fichier de configuration Apache/Nginx
2. Créer le répertoire si nécessaire
3. Recharger le serveur web
4. Ajouter l'entrée DNS (si possible via API)

### Exemple d'implémentation

```php
protected function createSubdomain(string $subdomain): void
{
    $baseDomain = config('app.subdomain_base_domain', 'medkey.local');
    $webRoot = config('app.subdomain_web_root', '/var/www/html');
    
    // Créer le fichier de configuration Apache
    $configContent = $this->generateApacheConfig($subdomain, $baseDomain, $webRoot);
    $configPath = "/etc/apache2/sites-available/{$subdomain}.conf";
    
    file_put_contents($configPath, $configContent);
    
    // Activer le site
    exec("sudo a2ensite {$subdomain}.conf");
    exec("sudo systemctl reload apache2");
    
    // Ajouter l'entrée dans /etc/hosts (pour le développement)
    if (config('app.env') === 'local') {
        $hostsEntry = "127.0.0.1 {$subdomain}.{$baseDomain}\n";
        file_put_contents('/etc/hosts', $hostsEntry, FILE_APPEND);
    }
}
```

## Sécurité

- Assurez-vous que les sous-domaines ne peuvent pas accéder aux fichiers sensibles
- Utilisez HTTPS en production avec des certificats SSL (Let's Encrypt)
- Validez et nettoyez les noms de sous-domaines pour éviter les injections
