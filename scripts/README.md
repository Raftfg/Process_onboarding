# Scripts d'Installation

Ce dossier contient les scripts d'installation et de configuration pour le projet.

## setup-acrylic.ps1

Script PowerShell pour configurer automatiquement Acrylic DNS Proxy sur Windows.

### Utilisation

**Installation/Configuration :**
```powershell
# Exécutez en tant qu'administrateur
.\scripts\setup-acrylic.ps1
```

**Désinstallation :**
```powershell
# Exécutez en tant qu'administrateur
.\scripts\setup-acrylic.ps1 -Uninstall
```

### Ce que fait le script

1. ✅ Vérifie que Acrylic DNS Proxy est installé
2. ✅ Configure Acrylic pour supporter `*.localhost`
3. ✅ Configure Windows DNS pour utiliser Acrylic (127.0.0.1)
4. ✅ Démarre le service Acrylic
5. ✅ Configure le démarrage automatique
6. ✅ Teste la résolution DNS

### Prérequis

- Windows 10/11
- Acrylic DNS Proxy installé (téléchargez depuis https://sourceforge.net/projects/acrylic/)
- Droits administrateur

### Dépannage

Si le script échoue :

1. Vérifiez que vous exécutez PowerShell en tant qu'administrateur
2. Vérifiez que Acrylic DNS Proxy est installé dans `C:\Program Files (x86)\Acrylic DNS Proxy\`
3. Consultez [ACRYLIC_DNS_SETUP.md](../ACRYLIC_DNS_SETUP.md) pour la configuration manuelle

