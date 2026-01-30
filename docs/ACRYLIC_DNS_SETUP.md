# Configuration Acrylic DNS Proxy pour les Sous-domaines Locaux

Ce guide explique comment installer et configurer Acrylic DNS Proxy pour supporter les wildcards DNS (`*.localhost`) en développement local sur Windows.

## 📋 Prérequis

- Windows 10/11
- Droits administrateur
- Laravel avec `php artisan serve` ou un serveur web local

## 🚀 Installation

### Étape 1 : Télécharger Acrylic DNS Proxy

1. Téléchargez Acrylic DNS Proxy depuis : https://sourceforge.net/projects/acrylic/
2. Ou utilisez Chocolatey :
   ```powershell
   choco install acrylic-dns-proxy
   ```

### Étape 2 : Installer Acrylic

1. Exécutez l'installateur `AcrylicSetup.exe` en tant qu'administrateur
2. Suivez l'assistant d'installation
3. Par défaut, Acrylic s'installe dans `C:\Program Files (x86)\Acrylic DNS Proxy\`

### Étape 3 : Configurer Acrylic

#### Option A : Configuration Automatique (Recommandé)

Exécutez le script PowerShell fourni :

```powershell
# Dans PowerShell en tant qu'administrateur
.\scripts\setup-acrylic.ps1
```

#### Option B : Configuration Manuelle

1. Ouvrez le fichier de configuration Acrylic :
   - Chemin : `C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicConfiguration.ini`
   - Ou via le menu Démarrer : `Acrylic DNS Proxy` → `Open Configuration File`

2. Ajoutez la règle suivante dans la section `[Hosts]` :

   ```ini
   [Hosts]
   127.0.0.1 *.localhost
   ```

3. Sauvegardez le fichier

### Étape 4 : Configurer Windows pour utiliser Acrylic

1. Ouvrez les **Paramètres réseau** de Windows
2. Allez dans **Paramètres réseau avancés** → **Modifier les options de la carte**
3. Cliquez droit sur votre connexion réseau active → **Propriétés**
4. Sélectionnez **Protocole Internet version 4 (TCP/IPv4)** → **Propriétés**
5. Sélectionnez **Utiliser l'adresse de serveur DNS suivante**
6. Entrez :
   - **Serveur DNS préféré** : `127.0.0.1`
   - **Serveur DNS auxiliaire** : `8.8.8.8` (Google DNS) ou laissez vide
7. Cliquez sur **OK**

**Alternative via PowerShell (en tant qu'administrateur) :**

```powershell
# Obtenir l'index de votre interface réseau
Get-NetAdapter | Select-Object Name, InterfaceIndex

# Configurer DNS (remplacez <InterfaceIndex> par votre index)
Set-DnsClientServerAddress -InterfaceIndex <InterfaceIndex> -ServerAddresses 127.0.0.1,8.8.8.8
```

### Étape 5 : Démarrer le service Acrylic

1. Ouvrez le **Gestionnaire de services** Windows (`services.msc`)
2. Trouvez le service **Acrylic DNS Proxy**
3. Cliquez droit → **Démarrer** (ou **Redémarrer** s'il est déjà démarré)
4. Assurez-vous que le type de démarrage est défini sur **Automatique**

**Via PowerShell (en tant qu'administrateur) :**

```powershell
# Démarrer le service
Start-Service AcrylicService

# Vérifier le statut
Get-Service AcrylicService

# Configurer le démarrage automatique
Set-Service AcrylicService -StartupType Automatic
```

## ✅ Vérification

### Test 1 : Vérifier que Acrylic fonctionne

```powershell
# Tester une résolution DNS
nslookup test.localhost 127.0.0.1
```

Vous devriez voir :
```
Nom:    test.localhost
Address:  127.0.0.1
```

### Test 2 : Tester avec votre application

1. Démarrez votre serveur Laravel :
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. Créez un compte via l'onboarding

3. Vérifiez que la redirection fonctionne vers `http://tobi-melvin-1769757006.localhost:8000/dashboard`

### Test 3 : Vérifier les logs Acrylic

Les logs se trouvent dans :
- `C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicHosts.txt` (cache DNS)
- `C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicUI.exe` (interface graphique avec logs)

## 🔧 Configuration Avancée

### Ajouter d'autres domaines locaux

Éditez `AcrylicConfiguration.ini` et ajoutez :

```ini
[Hosts]
127.0.0.1 *.localhost
127.0.0.1 *.local
127.0.0.1 *.dev
```

### Configurer le cache DNS

Dans `AcrylicConfiguration.ini` :

```ini
[AcrylicConfiguration]
CacheSize=1048576
CacheFile=AcrylicHosts.txt
```

### Désactiver temporairement Acrylic

1. Ouvrez le **Gestionnaire de services**
2. Arrêtez le service **Acrylic DNS Proxy**
3. Remettez vos paramètres DNS Windows à **Obtenir automatiquement l'adresse du serveur DNS**

## 🐛 Dépannage

### Le service ne démarre pas

1. Vérifiez que le port 53 n'est pas utilisé par un autre service :
   ```powershell
   netstat -ano | findstr :53
   ```

2. Si un autre service utilise le port 53, arrêtez-le ou changez le port dans Acrylic

### Les sous-domaines ne se résolvent pas

1. Vérifiez que le service Acrylic est démarré :
   ```powershell
   Get-Service AcrylicService
   ```

2. Vérifiez la configuration DNS Windows :
   ```powershell
   Get-DnsClientServerAddress
   ```

3. Videz le cache DNS Windows :
   ```powershell
   ipconfig /flushdns
   ```

4. Redémarrez le service Acrylic :
   ```powershell
   Restart-Service AcrylicService
   ```

### Erreur "Access Denied"

Assurez-vous d'exécuter PowerShell en tant qu'administrateur :
- Clic droit sur PowerShell → **Exécuter en tant qu'administrateur**

### Les sous-domaines fonctionnent mais le serveur ne répond pas

Vérifiez que votre serveur Laravel écoute sur toutes les interfaces :
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## 📝 Notes Importantes

1. **Sécurité** : Acrylic DNS Proxy ne doit être utilisé qu'en développement local. Ne l'utilisez pas en production.

2. **Performance** : Acrylic met en cache les résolutions DNS, ce qui améliore les performances.

3. **Conflits** : Si vous utilisez un VPN, vous devrez peut-être ajuster la configuration DNS.

4. **Firewall** : Assurez-vous que Windows Firewall autorise Acrylic à écouter sur le port 53.

## 🔄 Désinstallation

Si vous souhaitez désinstaller Acrylic :

1. Arrêtez le service :
   ```powershell
   Stop-Service AcrylicService
   ```

2. Remettez les paramètres DNS Windows à **Automatique**

3. Désinstallez via **Paramètres** → **Applications** → **Acrylic DNS Proxy**

## 📚 Ressources

- [Documentation officielle Acrylic](https://sourceforge.net/projects/acrylic/)
- [Forum Acrylic](https://sourceforge.net/projects/acrylic/forums)

## 🆘 Support

Si vous rencontrez des problèmes :

1. Vérifiez les logs Acrylic
2. Vérifiez que le service est démarré
3. Vérifiez la configuration DNS Windows
4. Consultez la section Dépannage ci-dessus

