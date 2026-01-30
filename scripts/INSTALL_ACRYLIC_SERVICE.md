# Installation du Service Acrylic DNS Proxy

Si le script `setup-acrylic.ps1` indique que le service Acrylic n'est pas trouvé, suivez ces étapes pour l'installer manuellement.

## Méthode 1 : Via la ligne de commande (Recommandé)

1. Ouvrez PowerShell en tant qu'administrateur
2. Exécutez la commande suivante :

```powershell
& "C:\Program Files (x86)\Acrylic DNS Proxy\AcrylicService.exe" -install
```

3. Vérifiez que le service est installé :

```powershell
Get-Service | Where-Object { $_.DisplayName -like "*Acrylic*" }
```

4. Démarrez le service :

```powershell
Start-Service AcrylicService
```

5. Configurez le démarrage automatique :

```powershell
Set-Service AcrylicService -StartupType Automatic
```

## Méthode 2 : Via l'interface graphique

1. Ouvrez l'explorateur de fichiers
2. Naviguez vers : `C:\Program Files (x86)\Acrylic DNS Proxy\`
3. Double-cliquez sur `AcrylicUI.exe`
4. Dans l'interface, cliquez sur le bouton **"Install Service"** ou **"Installer le service"**
5. Confirmez l'installation si Windows demande des permissions

## Vérification

Après l'installation, vérifiez que le service fonctionne :

```powershell
# Vérifier le statut
Get-Service AcrylicService

# Tester la résolution DNS
nslookup test.localhost 127.0.0.1
```

Vous devriez voir `127.0.0.1` comme réponse.

## Relancer le script

Une fois le service installé, relancez le script de configuration :

```powershell
.\scripts\setup-acrylic.ps1
```

## Dépannage

### Erreur "Access Denied"

Assurez-vous d'exécuter PowerShell en tant qu'administrateur :
- Clic droit sur PowerShell → **Exécuter en tant qu'administrateur**

### Le service ne démarre pas

1. Vérifiez que le port 53 n'est pas utilisé :
   ```powershell
   netstat -ano | findstr :53
   ```

2. Si un autre service utilise le port 53, arrêtez-le ou changez la configuration Acrylic

### Le service est installé mais ne répond pas

1. Vérifiez les logs dans AcrylicUI
2. Redémarrez le service :
   ```powershell
   Restart-Service AcrylicService
   ```

3. Videz le cache DNS :
   ```powershell
   ipconfig /flushdns
   ```

