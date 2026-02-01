# Configuration des sous-domaines en local

## Format des URLs

Avec la nouvelle configuration, les URLs utilisent de vrais sous-domaines même en local :

- **Format** : `http://hopital-cotonou-1769760633.localhost:8000`
- **Exemples** :
  - Page de connexion : `http://hopital-cotonou-1769760633.localhost:8000/login`
  - Dashboard : `http://hopital-cotonou-1769760633.localhost:8000/dashboard`
  - Page de bienvenue : `http://hopital-cotonou-1769760633.localhost:8000/welcome`

**Note** : `localhost` fonctionne nativement dans les navigateurs modernes sans configuration supplémentaire.

## Configuration du fichier hosts (Windows)

Pour que les sous-domaines fonctionnent en local, vous devez configurer le fichier hosts Windows.

### Option 1 : Aucune configuration nécessaire (recommandé)

**Bonne nouvelle !** Avec le format `subdomain.localhost`, aucune configuration n'est nécessaire. Les navigateurs modernes (Chrome, Firefox, Edge) supportent nativement `*.localhost` et le résolvent automatiquement vers `127.0.0.1`.

Vous pouvez utiliser directement les URLs comme :
- `http://hopital-cotonou-1769760633.localhost:8000`
- `http://hopital-dassa-1769760383.localhost:8000`

### Option 2 : Configuration manuelle (si nécessaire)

Si vous rencontrez des problèmes avec certains navigateurs, vous pouvez configurer le fichier hosts :

1. Ouvrez le fichier hosts en tant qu'administrateur :
   - Chemin : `C:\Windows\System32\drivers\etc\hosts`
   - Clic droit → "Ouvrir avec" → Bloc-notes (en tant qu'administrateur)

2. Ajoutez chaque sous-domaine manuellement :
   ```
   127.0.0.1 hopital-cotonou-1769760633.localhost
   127.0.0.1 hopital-dassa-1769760383.localhost
   ```

### Option 2 : Utiliser un serveur DNS local (recommandé pour la production locale)

Pour éviter d'ajouter chaque sous-domaine manuellement, vous pouvez utiliser un serveur DNS local comme **Acrylic DNS** ou **DNSMasq**.

#### Avec Acrylic DNS (Windows)

1. Téléchargez et installez [Acrylic DNS](https://mayakron.altervista.org/wikibase/show.php?id=AcrylicHome)
2. Configurez Acrylic pour rediriger `*.127.0.0.1` vers `127.0.0.1`
3. Configurez Windows pour utiliser `127.0.0.1` comme serveur DNS principal

### Option 3 : Utiliser un domaine local personnalisé

Vous pouvez aussi utiliser un domaine local comme `akasigroup.local` :

1. Dans votre fichier hosts, ajoutez :
   ```
   127.0.0.1 akasigroup.local
   127.0.0.1 *.akasigroup.local
   ```

2. Modifiez `.env` :
   ```
   SUBDOMAIN_BASE_DOMAIN=akasigroup.local
   ```

3. Les URLs seront alors : `http://hopital-cotonou-1769760633.akasigroup.local:8000`

## Vérification

Pour vérifier que la configuration fonctionne :

1. Créez un nouvel onboarding
2. Notez le sous-domaine généré (ex: `hopital-cotonou-1769760633`)
3. Testez l'URL : `http://hopital-cotonou-1769760633.localhost:8000/login`

Si la page se charge, la configuration est correcte !

**Note** : Assurez-vous que votre serveur Laravel écoute sur toutes les interfaces :
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Dépannage

### Erreur "DNS_PROBE_FINISHED_NXDOMAIN"

- Vérifiez que le fichier hosts contient bien l'entrée pour votre sous-domaine
- Redémarrez votre navigateur après modification du fichier hosts
- Videz le cache DNS : `ipconfig /flushdns` (en tant qu'administrateur)

### La page ne se charge pas

- Vérifiez que le serveur Laravel est bien démarré : `php artisan serve --host=0.0.0.0 --port=8000`
- Vérifiez que le port 8000 n'est pas utilisé par un autre service
- Vérifiez les logs Laravel : `storage/logs/laravel.log`

## Notes importantes

- Le format avec timestamp (`-1769760633`) garantit l'unicité du sous-domaine
- En production, les sous-domaines utiliseront le vrai domaine configuré
- Le middleware `DetectTenant` extrait automatiquement le sous-domaine depuis l'URL
